<?php

namespace Drupal\course_register\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;

/**
 * Controller for handling payment returns.
 */
class VnpayController extends ControllerBase {

  /**
   * Xử lý response từ VNPAY.
   */
  public function vnpayReturn(Request $request) {
    try {
      // Lấy user_id từ query parameter
      $user_id = $request->query->get('username'); // vì param vẫn là username nhưng giá trị là user id
      if ($user_id) {
          // Load user từ user ID
          $user = \Drupal\user\Entity\User::load($user_id);
          
          if (!$user) {
              throw new \Exception('Không tìm thấy thông tin người dùng.');
          }
      }

      // Lấy tất cả query parameters từ VNPAY
      $vnpay_response = $request->query->all();

      // Verify response
      if (empty($vnpay_response['vnp_ResponseCode'])) {
        // return new JsonResponse([
        //   'status' => 'error',
        //   'message' => 'Không nhận được response từ VNPAY',
        // ], 400);
        return new TrustedRedirectResponse('http://localhost:5173/payment-result?vnp_ResponseCode=99&vnp_TransactionStatus=99');
      }

      // Verify hash từ VNPAY
      $vnp_SecureHash = $vnpay_response['vnp_SecureHash'] ?? '';
      $inputData = array_filter($vnpay_response, function($key) {
        return strpos($key, 'vnp_') === 0 && $key !== 'vnp_SecureHash';
      }, ARRAY_FILTER_USE_KEY);

      ksort($inputData);
      $hashData = "";
      foreach ($inputData as $key => $value) {
        $hashData .= $key . "=" . urlencode($value) . "&";
      }
      $hashData = rtrim($hashData, "&");

      // Lấy hash secret từ config
      $vnpay_config = \Drupal::service('settings')->get('vnpay_config');
      $secureHash = hash_hmac('sha512', $hashData, $vnpay_config['hash_secret']);

      if ($vnp_SecureHash !== $secureHash) {
        // return new JsonResponse([
        //   'status' => 'error',
        //   'message' => 'Secure hash không hợp lệ',
        // ], 400);
        return new TrustedRedirectResponse('http://localhost:5173/payment-result?vnp_ResponseCode=97&vnp_TransactionStatus=97');
      }

      // Kiểm tra response code
      if ($vnpay_response['vnp_ResponseCode'] !== '00') {
        // return new JsonResponse([
        //   'status' => 'error',
        //   'message' => 'Thanh toán thất bại',
        //   'vnpay_response' => [
        //     'response_code' => $vnpay_response['vnp_ResponseCode'],
        //     'transaction_no' => $vnpay_response['vnp_TransactionNo'] ?? '',
        //     'bank_code' => $vnpay_response['vnp_BankCode'] ?? '',
        //     'payment_date' => $vnpay_response['vnp_PayDate'] ?? '',
        //   ],
        // ], 400);
        return new TrustedRedirectResponse('http://localhost:5173/payment-result?' . http_build_query([
          'vnp_ResponseCode' => $vnpay_response['vnp_ResponseCode'],
          'vnp_TransactionStatus' => $vnpay_response['vnp_TransactionStatus'] ?? '99'
        ]));
      }

      // Lấy class_code và user_id từ vnp_OrderInfo
      $order_info = json_decode($vnpay_response['vnp_OrderInfo'], true);
      if (empty($order_info) || empty($order_info['class_code']) || empty($order_info['user_id'])) {
        throw new \Exception('Không tìm thấy thông tin mã lớp học hoặc người dùng.');
      }

      $class_code = $order_info['class_code'];
      $user_id = $order_info['user_id'];

      // Load user
      $user = \Drupal\user\Entity\User::load($user_id);
      if (!$user) {
        throw new \Exception('Không tìm thấy thông tin người dùng.');
      }

      // Tìm class node
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'class')
        ->condition('field_class_code', $class_code)
        ->accessCheck(TRUE)
        ->range(0, 1);

      $results = $query->execute();
      if (empty($results)) {
        throw new \Exception('Không tìm thấy lớp học.');
      }

      $class_id = reset($results);
      $class = Node::load($class_id);

      // Lưu transaction history với user_id từ order_info
      // Lưu transaction history với user đã load
      $transaction = Node::create([
        'type' => 'transaction_history',
        'title' => sprintf('[VNPAY] %s - %s',
            $class->label(),
            $user->getDisplayName()
        ),
        'field_transaction_date' => date('Y-m-d\TH:i:s'),
        'field_transaction_id' => $vnpay_response['vnp_TxnRef'],
        'field_transaction_method' => 'vnpay',
        'field_transaction_user' => ['target_id' => $user->id()],
        'field_transaction_course' => ['target_id' => $class_id],
        'field_vnpay_transaction_no' => $vnpay_response['vnp_TransactionNo'],
        'field_vnpay_txn_ref' => $vnpay_response['vnp_TxnRef'],
        'status' => 1,
      ]);
      $transaction->save();

      // Kiểm tra đăng ký trùng với user_id đã load
      $existing_registration = \Drupal::entityQuery('node')
        ->condition('type', 'class_registered')
        ->condition('field_class_registered', $class_id)
        ->condition('field_user_class_registered', $user->id())
        ->accessCheck(TRUE)
        ->range(0, 1)
        ->execute();

      if (!empty($existing_registration)) {
        throw new \Exception('Bạn đã đăng ký lớp học này rồi.');
      }

      // Kiểm tra số lượng học viên
      $current_participants = (int) $class->get('field_current_num_of_participant')->value;
      $max_participants = (int) $class->get('field_max_num_of_participant')->value;

      if ($current_participants >= $max_participants) {
        throw new \Exception('Lớp học đã đầy.');
      }

      // Tạo đăng ký lớp học với user đã load
      $class_registered = Node::create([
        'type' => 'class_registered',
        'title' => $class->label() . ' - ' . $user->getDisplayName(),
        'field_class_registered' => ['target_id' => $class_id],
        'field_user_class_registered' => ['target_id' => $user->id()],
        'status' => 1,
      ]);
      $class_registered->save();

      // Cập nhật số lượng học viên
      $class->set('field_current_num_of_participant', $current_participants + 1);
      $class->save();

      // Trả về response thành công
      // return new JsonResponse([
      //   'status' => 'success',
      //   'message' => 'Thanh toán và đăng ký lớp học thành công',
      //   'data' => [
      //     'transaction_id' => $vnpay_response['vnp_TxnRef'],
      //     'transaction_no' => $vnpay_response['vnp_TransactionNo'],
      //     'amount' => $vnpay_response['vnp_Amount'] ? (int)$vnpay_response['vnp_Amount'] / 100 : 0,
      //     'bank_code' => $vnpay_response['vnp_BankCode'] ?? '',
      //     'payment_date' => $vnpay_response['vnp_PayDate'] ?? '',
      //     'order_info' => $vnpay_response['vnp_OrderInfo'] ?? '',
      //     'registration_id' => $class_registered->id(),
      //     'class_code' => $class_code,
      //     'class_name' => $class->label(),
      //   ],
      // ], 200);
      return new TrustedRedirectResponse('http://localhost:5173/payment-result?' . http_build_query([
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00'
      ]));

    } catch (\Exception $e) {
      \Drupal::logger('course_register')->error('Lỗi xử lý VNPAY return: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
      ], 500);
    }
  }
}
