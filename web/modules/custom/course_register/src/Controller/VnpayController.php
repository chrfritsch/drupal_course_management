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
   * Lấy học kỳ hiện tại.
   */
//  private function getCurrentSemester() {
//    $current_date = new \DateTime();
//
//    // Query term học kỳ dựa trên ngày hiện tại
//    $query = \Drupal::entityQuery('taxonomy_term')
//      ->condition('vid', 'semester')
//      ->condition('field_semester_start_date', $current_date->format('Y-m-d'), '<=')
//      ->condition('field_semester_end_date', $current_date->format('Y-m-d'), '>=')
//      ->accessCheck(TRUE)
//      ->range(0, 1);
//
//    $tids = $query->execute();
//
//    if (!empty($tids)) {
//      return \Drupal\taxonomy\Entity\Term::load(reset($tids));
//    }
//
//    // Nếu không tìm thấy học kỳ hiện tại, kiểm tra xem có học kỳ trong năm học không
//    return $this->checkAndCreateSemester();
//  }

  /**
   * Kiểm tra và tạo học kỳ mới nếu cần.
   */
//  private function checkAndCreateSemester() {
//    $current_date = new \DateTime();
//    $year = (int) $current_date->format('Y');
//    $month = (int) $current_date->format('n');
//
//    // Xác định học kỳ và thời gian
//    if ($month >= 8 && $month <= 12) {
//      $semester_name = "Học kỳ I ($year-" . ($year + 1) . ")";
//      $academic_year = "$year-" . ($year + 1);
//    }
//    elseif ($month >= 1 && $month <= 5) {
//      $semester_name = "Học kỳ II (" . ($year - 1) . "-$year)";
//      $academic_year = ($year - 1) . "-$year";
//    }
//    else {
//      $semester_name = "Học kỳ hè ($year)";
//      $academic_year = ($year - 1) . "-$year";
//    }
//
//    // Kiểm tra xem học kỳ này đã tồn tại trong năm học chưa
//    $query = \Drupal::entityQuery('taxonomy_term')
//      ->condition('vid', 'semester')
//      ->condition('name', $semester_name)
//      ->condition('field_academic_year', $academic_year)
//      ->accessCheck(TRUE)
//      ->range(0, 1);
//
//    $tids = $query->execute();
//
//    if (!empty($tids)) {
//      return \Drupal\taxonomy\Entity\Term::load(reset($tids));
//    }
//
//    // Nếu chưa có thì tạo mới
//    if ($month >= 8 && $month <= 12) {
//      $start_date = "$year-08-01";
//      $end_date = "$year-12-31";
//    }
//    elseif ($month >= 1 && $month <= 5) {
//      $start_date = "$year-01-01";
//      $end_date = "$year-05-31";
//    }
//    else {
//      $start_date = "$year-06-01";
//      $end_date = "$year-07-31";
//    }
//
//    $term = \Drupal\taxonomy\Entity\Term::create([
//      'vid' => 'semester',
//      'name' => $semester_name,
//      'field_academic_year' => $academic_year,
//      'field_semester_start_date' => [
//        'value' => $start_date . 'T00:00:00',
//      ],
//      'field_semester_end_date' => [
//        'value' => $end_date . 'T23:59:59',
//      ],
//    ]);
//
//    $term->save();
//    return $term;
//  }

  /**
   * Xử lý response từ VNPAY.
   */
  public function vnpayReturn(Request $request) {
    try {
      // Lấy user_id từ query parameter
      $user_id = $request->query->get('username');
      if (!$user_id) {
        throw new \Exception('Thiếu thông tin người dùng.');
      }

      // Load user
      $user = \Drupal\user\Entity\User::load($user_id);
      if (!$user) {
        throw new \Exception('Không tìm thấy thông tin người dùng.');
      }

      // Lấy và verify response từ VNPAY
      $vnpay_response = $request->query->all();
      if (empty($vnpay_response['vnp_ResponseCode'])) {
        return new TrustedRedirectResponse('http://localhost:5173/payment-result?vnp_ResponseCode=99&vnp_TransactionStatus=99');
      }

      // Verify hash
      if (!$this->verifyVnpayHash($vnpay_response)) {
        return new TrustedRedirectResponse('http://localhost:5173/payment-result?vnp_ResponseCode=97&vnp_TransactionStatus=97');
      }

      // Kiểm tra response code
      if ($vnpay_response['vnp_ResponseCode'] !== '00') {
        return new TrustedRedirectResponse('http://localhost:5173/payment-result?' . http_build_query([
            'vnp_ResponseCode' => $vnpay_response['vnp_ResponseCode'],
            'vnp_TransactionStatus' => $vnpay_response['vnp_TransactionStatus'] ?? '99',
          ]));
      }

      // Xử lý đăng ký lớp học
      $this->processClassRegistration($vnpay_response, $user);

      return new TrustedRedirectResponse('http://localhost:5173/payment-result?' . http_build_query([
          'vnp_ResponseCode' => '00',
          'vnp_TransactionStatus' => '00',
        ]));
    }
    catch (\Exception $e) {
      \Drupal::logger('course_register')
        ->error('Lỗi xử lý VNPAY return: @error', [
          '@error' => $e->getMessage(),
        ]);

      return new TrustedRedirectResponse('http://localhost:5173/payment-result?vnp_ResponseCode=99&vnp_TransactionStatus=99');
    }
  }

  // Thêm các method helper
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

  private function processClassRegistration(array $vnpay_response, $user): void {
    // Lấy thông tin từ vnp_OrderInfo
    $order_info = json_decode($vnpay_response['vnp_OrderInfo'], TRUE);
    if (empty($order_info) || empty($order_info['class_code'])) {
      throw new \Exception('Không tìm thấy thông tin mã lớp học.');
    }

    // Tìm class node
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'class')
      ->condition('field_class_code', $order_info['class_code'])
      ->accessCheck(TRUE)
      ->range(0, 1);

    $results = $query->execute();
    if (empty($results)) {
      throw new \Exception('Không tìm thấy lớp học.');
    }

    $class = Node::load(reset($results));

    // Kiểm tra và tạo đăng ký lớp học
    $this->validateAndCreateRegistration($class, $user, $vnpay_response);
  }

  private function validateAndCreateRegistration($class, $user, array $vnpay_response): void {
    // Kiểm tra đăng ký trùng
    $course_id = $class->get('field_class_course_reference')->target_id;
    $course = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($course_id);

    if (!$course) {
      throw new \Exception('Không tìm thấy thông tin khóa học.');
    }

    $course_semester = $course->get('field_course_semester')->target_id;
    $existing_registration = \Drupal::entityQuery('node')
      ->condition('type', 'class_registered')
      ->condition('field_class_registered', $class->id())
      ->condition('field_user_class_registered', $user->id())
      ->condition('field_class_registered_semester', $course_semester)
      ->accessCheck(TRUE)
      ->range(0, 1)
      ->execute();

    if (!empty($existing_registration)) {
      throw new \Exception('Bạn đã đăng ký lớp học này trong học kỳ này rồi.');
    }

    // Kiểm tra số lượng học viên
    $current_participants = (int) $class->get('field_current_num_of_participant')->value;
    $max_participants = (int) $class->get('field_max_num_of_participant')->value;

    if ($current_participants >= $max_participants) {
      throw new \Exception('Lớp học đã đầy.');
    }

    // Lưu transaction history
    $this->saveTransactionHistory($class, $user, $vnpay_response);

    // Tạo đăng ký lớp học
    $this->createClassRegistration($class, $user);
  }

  /**
   * Lưu lịch sử giao dịch.
   */
  private function saveTransactionHistory($class, $user, array $vnpay_response): void {
    // Load thông tin course
    $course_id = $class->get('field_class_course_reference')->target_id;
    $course = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($course_id);

    if (!$course) {
      throw new \Exception('Không tìm thấy thông tin khóa học.');
    }

    // Tạo node transaction history
    $transaction = Node::create([
      'type' => 'transaction_history',
      'title' => 'Thanh toán VNPAY - ' . $class->label(),
      'field_transaction_amount' => $vnpay_response['vnp_Amount'] / 100,
      // VNPAY trả về số tiền * 100
      'field_transaction_id' => $vnpay_response['vnp_TransactionNo'],
      'field_transaction_date' => date('Y-m-d\TH:i:s', strtotime($vnpay_response['vnp_PayDate'])),
      'field_transaction_method' => 'vnpay',
      'field_transaction_status' => 'completed',
      'field_transaction_user' => $user->id(),
      'field_transaction_class' => $class->id(),
      'field_transaction_course' => $course_id,
      'field_transaction_semester' => $course->get('field_course_semester')->target_id,
    ]);

    $transaction->save();
  }

  /**
   * Tạo đăng ký lớp học.
   */
  private function createClassRegistration($class, $user): void {
    // Load thông tin course
    $course_id = $class->get('field_class_course_reference')->target_id;
    $course = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($course_id);

    if (!$course) {
      throw new \Exception('Không tìm thấy thông tin khóa học.');
    }

    // Tạo node class registered
    $class_registered = Node::create([
      'type' => 'class_registered',
      'title' => 'Đăng ký ' . $class->label() . ' - ' . $user->getDisplayName(),
      'field_class_registered' => $class->id(),
      'field_user_class_registered' => $user->id(),
      'field_class_registered_semester' => $course->get('field_course_semester')->target_id,
      'field_class_registered_status' => 'active',
      'field_class_registered_date' => date('Y-m-d\TH:i:s'),
    ]);

    $class_registered->save();

    // Cập nhật số lượng học viên trong lớp
    $current_participants = (int) $class->get('field_current_num_of_participant')->value;
    $class->set('field_current_num_of_participant', $current_participants + 1);
    $class->save();
  }

}
