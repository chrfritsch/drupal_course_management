<?php

declare(strict_types=1);

namespace Drupal\course_register\Plugin\rest\resource;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;

/**
 * Represents Register course API records as resources.
 *
 * @RestResource (
 *   id = "course_register_register_course_api",
 *   label = @Translation("Register course API"),
 *   uri_paths = {
 *     "create" = "/api/course-register-register-course-api",
 *     "canonical" = "/api/course-register-register-course-api/{id}"
 *   }
 * )
 */
final class RegisterCourseApiResource extends ResourceBase {

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $storage;

  /**
   * Current user.
   */
  protected $currentUser;

  /**
   * VNPAY config.
   */
  private array $vnpayConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    KeyValueFactoryInterface $keyValueFactory,
    AccountProxyInterface $currentUser
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->storage = $keyValueFactory->get('course_register_register_course_api');
    $this->currentUser = $currentUser;
    $this->vnpayConfig = \Drupal::service('settings')->get('vnpay_config');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('keyvalue'),
      $container->get('current_user')
    );
  }

  /**
   * Lấy học kỳ hiện tại.
   */
  private function getCurrentSemester() {
    $current_date = new \DateTime();

    // Query term học kỳ dựa trên ngày hiện tại
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'semester')
      ->condition('field_semester_start_date', $current_date->format('Y-m-d'), '<=')
      ->condition('field_semester_end_date', $current_date->format('Y-m-d'), '>=')
      ->accessCheck(TRUE)
      ->range(0, 1);

    $tids = $query->execute();

    if (!empty($tids)) {
      return \Drupal\taxonomy\Entity\Term::load(reset($tids));
    }

    // Nếu không tìm thấy học kỳ hiện tại, kiểm tra xem có học kỳ trong năm học không
    return $this->checkAndCreateSemester();
  }

  /**
   * Kiểm tra và tạo học kỳ mới nếu cần.
   */
  private function checkAndCreateSemester() {
    $current_date = new \DateTime();
    $year = (int) $current_date->format('Y');
    $month = (int) $current_date->format('n');

    // Xác định học kỳ và thời gian
    if ($month >= 8 && $month <= 12) {
      $semester_name = "Học kỳ I ($year-" . ($year + 1) . ")";
      $academic_year = "$year-" . ($year + 1);
    }
    elseif ($month >= 1 && $month <= 5) {
      $semester_name = "Học kỳ II (" . ($year - 1) . "-$year)";
      $academic_year = ($year - 1) . "-$year";
    }
    else {
      $semester_name = "Học kỳ hè ($year)";
      $academic_year = ($year - 1) . "-$year";
    }

    // Kiểm tra xem học kỳ này đã tồn tại trong năm học chưa
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'semester')
      ->condition('name', $semester_name)
      ->condition('field_academic_year', $academic_year)
      ->accessCheck(TRUE)
      ->range(0, 1);

    $tids = $query->execute();

    if (!empty($tids)) {
      return \Drupal\taxonomy\Entity\Term::load(reset($tids));
    }

    // Nếu chưa có thì tạo mới
    if ($month >= 8 && $month <= 12) {
      $start_date = "$year-08-01";
      $end_date = "$year-12-31";
    }
    elseif ($month >= 1 && $month <= 5) {
      $start_date = "$year-01-01";
      $end_date = "$year-05-31";
    }
    else {
      $start_date = "$year-06-01";
      $end_date = "$year-07-31";
    }

    $term = \Drupal\taxonomy\Entity\Term::create([
      'vid' => 'semester',
      'name' => $semester_name,
      'field_academic_year' => $academic_year,
      'field_semester_start_date' => [
        'value' => $start_date . 'T00:00:00',
      ],
      'field_semester_end_date' => [
        'value' => $end_date . 'T23:59:59',
      ],
    ]);

    $term->save();
    return $term;
  }

  /**
   * Tạo URL thanh toán VNPAY.
   */
  private function createVnpayPaymentUrl($order_info) {
    // Lấy thông tin user hiện tại
    $current_user = $this->currentUser->id(); // lấy username

    $vnp_TxnRef = date("YmdHis");
    $vnp_OrderInfo = json_encode([
      'class_code' => $order_info['class_code'],
      'user_id' => $current_user,
      'description' => 'Thanh toan khoa hoc',
    ]);
    $vnp_OrderType = "billpayment";
    $vnp_Amount = (int) ($order_info['amount'] * 100);
    $vnp_Locale = "vn";
    $vnp_ReturnUrl = \Drupal::request()
        ->getSchemeAndHttpHost() . "/payment/vnpay-return?username=" . urlencode((string) $current_user);
    $vnp_IpAddr = \Drupal::request()->getClientIp();

    $inputData = [
      "vnp_Version" => "2.1.0",
      "vnp_TmnCode" => $this->vnpayConfig['tmn_code'],
      "vnp_Amount" => (string) $vnp_Amount,
      "vnp_Command" => "pay",
      "vnp_CreateDate" => date('YmdHis'),
      "vnp_CurrCode" => "VND",
      "vnp_IpAddr" => $vnp_IpAddr,
      "vnp_Locale" => $vnp_Locale,
      "vnp_OrderInfo" => $vnp_OrderInfo,
      "vnp_OrderType" => $vnp_OrderType,
      "vnp_ReturnUrl" => $vnp_ReturnUrl,
      "vnp_TxnRef" => $vnp_TxnRef,
    ];

    ksort($inputData);
    $query = "";
    foreach ($inputData as $key => $value) {
      $query .= urlencode($key) . "=" . urlencode((string) $value) . "&";
    }

    $vnp_SecureHash = hash_hmac('sha512', rtrim($query, '&'), $this->vnpayConfig['hash_secret']);
    $query .= 'vnp_SecureHash=' . $vnp_SecureHash;

    return $this->vnpayConfig['payment_url'] . "?" . $query;
  }

  /**
   * Xác thực giao dịch VNPay.
   */
  // private function verifyVnpayTransaction(array $vnpay_response): bool {
  //   try {
  //     if ($vnpay_response['vnp_ResponseCode'] !== '00') {
  //       $this->logger->error('VNPAY response code không hợp lệ: @code', [
  //         '@code' => $vnpay_response['vnp_ResponseCode'],
  //       ]);
  //       return FALSE;
  //     }

  //     $vnp_SecureHash = $vnpay_response['vnp_SecureHash'];
  //     $inputData = array_filter($vnpay_response, function($key) {
  //       return strpos($key, 'vnp_') === 0 && $key !== 'vnp_SecureHash';
  //     }, ARRAY_FILTER_USE_KEY);

  //     ksort($inputData);
  //     $hashData = "";
  //     foreach ($inputData as $key => $value) {
  //       $hashData .= $key . "=" . $value . "&";
  //     }
  //     $hashData = rtrim($hashData, "&");

  //     $secureHash = hash_hmac('sha512', $hashData, $this->vnpayConfig['hash_secret']);

  //     if ($vnp_SecureHash !== $secureHash) {
  //       $this->logger->error('VNPAY secure hash không khớp');
  //       return FALSE;
  //     }

  //     return TRUE;
  //   }
  //   catch (\Exception $e) {
  //     $this->logger->error('Lỗi khi xác thực VNPAY: @error', [
  //       '@error' => $e->getMessage(),
  //     ]);
  //     return FALSE;
  //   }
  // }

  /**
   * Xác thực giao dịch PayPal.
   */
  private function verifyPaypalTransaction(string $transaction_id): bool {
    try {
      // TODO: Implement PayPal verification logic here
      // Gọi PayPal API để verify transaction
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Lỗi khi xác thực PayPal: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Tạo transaction history cho giao dịch VNPAY.
   */
  // private function createVnpayTransactionHistory(
  //   int $class_id,
  //   string $transaction_id,
  //   string $class_name,
  //   array $vnpay_response
  // ): void {
  //   $payment_data = [
  //     'vnp_TransactionNo' => $vnpay_response['vnp_TransactionNo'] ?? '',
  //     'vnp_TxnRef' => $vnpay_response['vnp_TxnRef'] ?? '',
  //     'vnp_ResponseCode' => $vnpay_response['vnp_ResponseCode'] ?? '',
  //     'vnp_Amount' => $vnpay_response['vnp_Amount'] ?? '',
  //   ];

  //   $query = \Drupal::entityQuery('node')
  //     ->condition('type', 'transaction_history')
  //     ->condition('field_transaction_id', $transaction_id)
  //     ->condition('field_transaction_method', 'vnpay')
  //     ->accessCheck(TRUE)
  //     ->range(0, 1);

  //   $results = $query->execute();

  //   if (!empty($results)) {
  //     return;
  //   }

  //   $this->saveTransactionHistory(
  //     $class_id,
  //     $transaction_id,
  //     $class_name,
  //     'vnpay',
  //     $payment_data
  //   );
  // }

  /**
   * Tạo transaction history cho giao dịch PayPal.
   */
  private function createPaypalTransactionHistory(
    int $class_id,
    string $transaction_id,
    string $class_name,
    array $paypal_data = []
  ): void {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'transaction_history')
      ->condition('field_transaction_id', $transaction_id)
      ->condition('field_transaction_method', 'paypal')
      ->accessCheck(TRUE)
      ->range(0, 1);

    $results = $query->execute();

    if (!empty($results)) {
      return;
    }

    $this->saveTransactionHistory(
      $class_id,
      $transaction_id,
      $class_name,
      'paypal',
      $paypal_data
    );
  }

  /**
   * Lưu thông tin transaction history.
   */
  private function saveTransactionHistory(
    int $class_id,
    string $transaction_id,
    string $class_name,
    string $payment_method,
    array $payment_data = []
  ): void {
    try {
      // Lấy học kỳ hiện tại
      $current_semester = $this->getCurrentSemester();

      $transaction = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->create([
          'type' => 'transaction_history',
          'title' => sprintf('[%s] %s - %s',
            strtoupper($payment_method),
            $class_name,
            $this->currentUser->getDisplayName()
          ),
          'field_transaction_course' => ['target_id' => $class_id],
          'field_transaction_date' => date('Y-m-d\TH:i:s'),
          'field_transaction_id' => $transaction_id,
          'field_transaction_method' => $payment_method,
          'field_transaction_user' => ['target_id' => $this->currentUser->id()],
          'field_vnpay_transaction_no' => $payment_data['vnp_TransactionNo'] ?? '',
          'field_vnpay_txn_ref' => $payment_data['vnp_TxnRef'] ?? '',
          // Thêm field học kỳ
          'field_transaction_semester' => $current_semester ? ['target_id' => $current_semester->id()] : NULL,
          'status' => 1,
        ]);

      $transaction->save();
    }
    catch (\Exception $e) {
      $this->logger->error('Lỗi khi lưu transaction history: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Xử lý thanh toán qua VNPAY.
   */
  // private function handleVnpayPayment(array $data, $class) {
  //   // Bước 1: Tạo URL thanh toán
  //   if (empty($data['payment_transaction_id'])) {
  //     $course_id = $class->get('field_class_course_reference')->target_id;
  //     $course = \Drupal::entityTypeManager()
  //       ->getStorage('node')
  //       ->load($course_id);

  //     if (!$course) {
  //       throw new HttpException(404, 'Không tìm thấy thông tin khóa học.');
  //     }

  //     $order_info = [
  //       'amount' => $course->get('field_course_tuition_fee')->value,
  //       'class_id' => $class->id(),
  //       'class_code' => $data['class_code'],
  //     ];

  //     $payment_url = $this->createVnpayPaymentUrl($order_info);

  //     return new ResourceResponse([
  //       'payment_url' => $payment_url,
  //     ]);
  //   }

  //   // Bước 2: Xử lý kết quả thanh toán
  //   $vnpay_response = \Drupal::request()->query->all();

  //   // Verify giao dịch
  //   if (!$this->verifyVnpayTransaction($vnpay_response)) {
  //     throw new HttpException(400, 'Giao dịch VNPAY không hợp lệ hoặc chưa được xác nhận.');
  //   }

  //   // Lưu transaction history
  //   $this->createVnpayTransactionHistory(
  //     $class->id(),
  //     $data['payment_transaction_id'],
  //     $class->label(),
  //     $vnpay_response
  //   );

  //   return TRUE;
  // }

  /**
   * Xử lý thanh toán qua PayPal.
   */
//  private function handlePaypalPayment(array $data, $class) {
//    if (empty($data['payment_transaction_id'])) {
//      throw new HttpException(400, 'Thiếu thông tin giao dịch PayPal.');
//    }
//
//    // Verify giao dịch PayPal
//    if (!$this->verifyPaypalTransaction($data['payment_transaction_id'])) {
//      throw new HttpException(400, 'Giao dịch PayPal không hợp lệ.');
//    }
//
//    // Lưu transaction history cho PayPal
//    $this->createPaypalTransactionHistory(
//      (int) $class->id(),
//      $data['payment_transaction_id'],
//      $class->label()
//    );
//
//    return TRUE;
//  }

  /**
   * Responds to POST requests.
   */
  public function post(array $data) {
    try {
      // Validate input data
      if (empty($data['class_code'])) {
        throw new HttpException(400, 'Mã lớp học không được để trống.');
      }
      if (empty($data['payment_method'])) {
        throw new HttpException(400, 'Phương thức thanh toán không được để trống.');
      }

      // Kiểm tra đăng nhập
      if (!$this->currentUser->isAuthenticated()) {
        throw new HttpException(403, 'Bạn cần đăng nhập để đăng ký lớp học.');
      }

      // Tìm class
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'class')
        ->condition('field_class_code', $data['class_code'])
        ->accessCheck(TRUE)
        ->range(0, 1);

      $results = $query->execute();

      if (empty($results)) {
        throw new HttpException(404, 'Không tìm thấy lớp học với mã lớp này.');
      }

      $class_id = (int) reset($results);
      $class = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($class_id);

      if (!$class) {
        throw new HttpException(404, 'Không tìm thấy thông tin lớp học.');
      }

      // Xử lý thanh toán dựa trên phương thức
      switch ($data['payment_method']) {
        case 'vnpay':
          // Với VNPAY, chỉ tạo URL thanh toán và trả về
          $course_id = $class->get('field_class_course_reference')->target_id;
          $course = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load($course_id);

          if (!$course) {
            throw new HttpException(404, 'Không tìm thấy thông tin khóa học.');
          }

          $order_info = [
            'amount' => $course->get('field_course_tuition_fee')->value,
            'class_code' => $data['class_code'],
          ];

          $payment_url = $this->createVnpayPaymentUrl($order_info);

          return new ResourceResponse([
            'payment_url' => $payment_url,
            'analytics_data' => [
              'class_code' => $data['class_code'],
              'class_name' => $class->label(),
              'course_code' => $course->get('field_course_code')->value,
              'course_name' => $course->label(),
              'amount' => $course->get('field_course_tuition_fee')->value,
              'currency' => 'VND',
              'payment_method' => 'vnpay',
              'student_id' => $this->currentUser->id(),
              'student_name' => $this->currentUser->getDisplayName(),
            ],
          ]);

        case 'paypal':
          if (empty($data['payment_transaction_id'])) {
            throw new HttpException(400, 'Thiếu thông tin giao dịch PayPal.');
          }

          // Load thông tin course trước khi sử dụng
          $course_id = $class->get('field_class_course_reference')->target_id;
          $course = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load($course_id);

          if (!$course) {
            throw new HttpException(404, 'Không tìm thấy thông tin khóa học.');
          }

          // Verify giao dịch PayPal
          if (!$this->verifyPaypalTransaction($data['payment_transaction_id'])) {
            throw new HttpException(400, 'Giao dịch PayPal không hợp lệ.');
          }

          // Tạo transaction history cho PayPal
          $this->createPaypalTransactionHistory(
            (int) $class->id(),
            $data['payment_transaction_id'],
            $class->label()
          );

          // Tạo class registration và cập nhật số lượng học viên
          $class_registered = $this->createClassRegistration($class, $this->currentUser);

          return new ResourceResponse([
            'message' => 'Đăng ký lớp học thành công',
            'data' => [
              'registration_id' => $class_registered->id(),
              'transaction_id' => $data['payment_transaction_id'],
              'payment_method' => 'paypal',
              'class_code' => $data['class_code'],
              'class_name' => $class->label(),
              'registered_at' => date('Y-m-d H:i:s'),
            ],
            'analytics_data' => [
              'transaction_id' => $data['payment_transaction_id'],
              'amount' => $course->get('field_course_tuition_fee')->value,
              'currency' => 'VND',
              'payment_method' => 'paypal',
              'class_code' => $data['class_code'],
              'class_name' => $class->label(),
              'course_code' => $course->get('field_course_code')->value,
              'course_name' => $course->label(),
              'payment_date' => date('Y-m-d H:i:s'),
              'student_id' => $this->currentUser->id(),
              'student_name' => $this->currentUser->getDisplayName(),
            ],
          ], 201);

        default:
          throw new HttpException(400, 'Phương thức thanh toán không hợp lệ.');
      }
    }
    catch (HttpException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $this->logger->error('Lỗi khi đăng ký lớp học: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw new HttpException(500, 'Có lỗi xảy ra khi đăng ký lớp học. Vui lòng thử lại sau.');
    }
  }

  /**
   * Tạo đăng ký lớp học và cập nhật số lượng học viên.
   */
  private function createClassRegistration($class, $user) {
    // Kiểm tra đăng ký trùng
    $course_id = $class->get('field_class_course_reference')->target_id;
    $course = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($course_id);

    if (!$course) {
      throw new HttpException(404, 'Không tìm thấy thông tin khóa học.');
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
      throw new HttpException(400, 'Bạn đã đăng ký lớp học này trong học kỳ này rồi.');
    }

    // Kiểm tra số lượng học viên
    $current_participants = (int) $class->get('field_current_num_of_participant')->value;
    $max_participants = (int) $class->get('field_max_num_of_participant')->value;

    if ($current_participants >= $max_participants) {
      throw new HttpException(400, 'Lớp học đã đầy.');
    }

    // Tạo đăng ký lớp học
    $class_registered = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->create([
        'type' => 'class_registered',
        'title' => $class->label() . ' - ' . $user->getDisplayName(),
        'field_class_registered' => ['target_id' => $class->id()],
        'field_user_class_registered' => ['target_id' => $user->id()],
        'field_class_registered_semester' => $this->getCurrentSemester() ? [
          'target_id' => $this->getCurrentSemester()->id(),
        ] : NULL,
        'status' => 1,
      ]);

    $class_registered->save();

    // Cập nhật số lượng học viên
    $class->set('field_current_num_of_participant', $current_participants + 1);
    $class->save();

    return $class_registered;
  }

}
