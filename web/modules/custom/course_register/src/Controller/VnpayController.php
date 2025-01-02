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
      #region Kiểm tra thong tin người dùng
      $user_id = $request->query->get('username');
      if (!$user_id) {
        throw new \Exception('Thiếu thông tin người dùng.');
      }
      #endregion

      #region Tìm thong tin người dùng trong CSDL, nếu không có thì báo lỗi
      $user = \Drupal\user\Entity\User::load($user_id);
      if (!$user) {
        throw new \Exception('Không tìm thấy thông tin người dùng.');
      }
      #endregion

      #region Lấy và xử lý thông tin trả về từ VNPAY
      $vnpay_response = $request->query->all();
      if (empty($vnpay_response['vnp_ResponseCode'])) {
        $error_data = [
          'status' => 'error',
          'code' => 99,
          'message' => 'Thiếu thông tin response từ VNPAY',
          'data' => [
            'vnp_ResponseCode' => 99,
            'vnp_TransactionStatus' => 99,
          ],
        ];
        $query = http_build_query($error_data);
        return new TrustedRedirectResponse('http://localhost:5173/payment/vnpay-return?' . $query);
      }
      #endregion

      // Verify hash
      if (!$this->verifyVnpayHash($vnpay_response)) {
        $error_data = [
          'status' => 'error',
          'code' => 97,
          'message' => 'Sai chữ ký từ VNPAY',
          'data' => [
            'vnp_ResponseCode' => 97,
            'vnp_TransactionStatus' => 97,
          ],
        ];
        $query = http_build_query($error_data);
        return new TrustedRedirectResponse('http://localhost:5173/payment/vnpay-return?' . $query);
      }

      // Kiểm tra response code
      if ($vnpay_response['vnp_ResponseCode'] !== '00') {
        $error_data = [
          'status' => 'error',
          'code' => $vnpay_response['vnp_ResponseCode'],
          'message' => 'Giao dịch thất bại',
          'data' => [
            'vnp_ResponseCode' => $vnpay_response['vnp_ResponseCode'],
            'vnp_TransactionStatus' => $vnpay_response['vnp_TransactionStatus'] ?? '99',
          ],
        ];
        $query = http_build_query($error_data);
        return new TrustedRedirectResponse('http://localhost:5173/payment/vnpay-return?' . $query);
      }

      #region Xác thực Thông tin đơn hàng
      $order_info = json_decode($vnpay_response['vnp_OrderInfo'], TRUE);
      if (!$order_info || empty($order_info['class_codes'])) {
        throw new \Exception('Thông tin đơn hàng không hợp lệ.');
      }
      #endregion

      $transactions = [];
      $class_ids = [];

      #region Dòng này là để chuyển lại số tiền ban đầu do VNPAY trả về là số tiền đã nhân 100
      $amount_per_class = $vnpay_response['vnp_Amount'] / 100 / count($order_info['class_codes']);
      #endregion

      // khai báo service tạo hóa đơn
      /** @var \Drupal\course_register\Service\ReceiptService $receipt_service */
      $receipt_service = \Drupal::service('course_register.receipt');

      foreach ($order_info['class_codes'] as $class_code) {
        #region Tìm lớp học
        $query = \Drupal::entityQuery('node')
          ->condition('type', 'class')
          ->condition('title', $class_code)
          ->accessCheck(TRUE)
          ->range(0, 1);

        $results = $query->execute();
        if (empty($results)) {
          throw new \Exception('Không tìm thấy lớp học: ' . $class_code);
        }
        #endregion

        $class_id = reset($results);
        $class_ids[] = $class_id;

        $class = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load($class_id);

        #region Cập nhật trạng thái đăng ký
        $registration_query = \Drupal::entityQuery('node')
          ->condition('type', 'class_registration')
          ->condition('field_registration_class', $class->id())
          ->condition('field_registration_user', $user->id())
          ->condition('field_registration_status', 'pending')
          ->accessCheck(TRUE)
          ->range(0, 1);

        $registration_results = $registration_query->execute();
        if (!empty($registration_results)) {
          $registration = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load(reset($registration_results));

          $registration->set('field_registration_status', 'confirmed');
          $registration->save();
        }

        #endregion

        // Tạo lịch su giao dịch
        $transaction = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->create([
            'type' => 'transaction_history',
            'title' => sprintf('[VNPAY] %s - %s',
              $class->label(),
              $user->getDisplayName()
            ),
            'field_transaction_course' => ['target_id' => $class->id()],
            'field_transaction_date' => date('Y-m-d\TH:i:s'),
            'field_transaction_id' => $vnpay_response['vnp_TransactionNo'],
            'field_transaction_method' => 'vnpay',
            'field_transaction_user' => ['target_id' => $user->id()],
            'field_transaction_amount' => $amount_per_class,
            'status' => 1,
          ]);

        $transaction->save();

        $transactions[] = [
          'transaction_id' => $vnpay_response['vnp_TransactionNo'],
          'class_code' => $class_code,
          'class_name' => $class->label(),
          'amount' => $amount_per_class,
        ];
      }

      // Tạo một receipt cho tất cả các lớp
      $receipt_data = [
        'amount' => $vnpay_response['vnp_Amount'] / 100,
        'payment_method' => 'vnpay',
        'transaction_id' => $vnpay_response['vnp_TransactionNo'],
        'user_id' => $user->id(),
        'class_ids' => $class_ids,
      ];

      $receipt = $receipt_service->createReceipt($receipt_data);

      // Cập nhật transactions array để thêm receipt_id
      foreach ($transactions as &$transaction) {
        $transaction['receipt_id'] = $receipt->id();
      }

      $response_data = [
        'status' => 'success',
        'code' => '00',
        'message' => 'Giao dịch thành công',
        'data' => [
          'vnp_ResponseCode' => '00',
          'vnp_TransactionStatus' => '00',
          'payment_date' => date('Y-m-d H:i:s'),
          'total_amount' => $vnpay_response['vnp_Amount'] / 100,
          'transactions' => $transactions,
          'student_name' => $user->getDisplayName(),
          'receipt_id' => $receipt->id(),
        ],
      ];

      $query = http_build_query($response_data);
      return new TrustedRedirectResponse('http://localhost:5173/payment/vnpay-return?' . $query);
    }
    catch (\Exception $e) {
      \Drupal::logger('course_register')
        ->error('Lỗi xử lý VNPAY return: @error', [
          '@error' => $e->getMessage(),
        ]);

        $error_data = [
          'status' => 'error',
          'code' => 99,
          'message' => $e->getMessage(),
          'data' => [
            'vnp_ResponseCode' => 99,
            'vnp_TransactionStatus' => 99,
          ],
        ];

        $query = http_build_query($error_data);
        return new TrustedRedirectResponse('http://localhost:5173/payment/vnpay-return?' . $query);
    }
  }

  /**
   * Verify hash từ VNPAY.
   */
  private function verifyVnpayHash(array $vnpay_response): bool {
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

    $vnpay_config = \Drupal::service('settings')->get('vnpay_config');
    $secureHash = hash_hmac('sha512', $hashData, $vnpay_config['hash_secret']);

    return $vnp_SecureHash === $secureHash;
  }

}
