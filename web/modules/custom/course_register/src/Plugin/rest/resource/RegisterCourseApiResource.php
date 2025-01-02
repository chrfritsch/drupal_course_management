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
 *     "create" = "/api/course-register-register-course-api"
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
   * Tạo URL thanh toán VNPAY.
   */
  private function createVnpayPaymentUrl($order_info) {
    $vnp_TxnRef = date("YmdHis");
    $vnp_OrderInfo = json_encode($order_info);
    $vnp_OrderType = "billpayment";
    $vnp_Amount = (int) ($order_info['amount'] * 100);
    $vnp_Locale = "vn";
    $vnp_ReturnUrl = \Drupal::request()
        ->getSchemeAndHttpHost() . "/payment/vnpay-return?username=" . $this->currentUser->id();
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
    $i = 0;
    $hashdata = "";
    foreach ($inputData as $key => $value) {
      if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
      }
      else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
      }
      $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }

    $vnp_Url = $this->vnpayConfig['payment_url'] . "?" . $query;
    $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnpayConfig['hash_secret']);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;

    return $vnp_Url;
  }

  /**
   * Hàm này là hàm nhận request từ front-end và bắt đầu tiến trình xử lý thanh toán.
   * Tham số $data chứa thông tin từ client gửi lên.
   *
   * Ví dụ xử lý thanh toán với VNPAY, mảng $data sẽ có dạng:
   * [
   *   'class_codes' => ['MATH101', 'PHYS101'],
   *   'payment_method' => 'vnpay'
   * ]
   *
   * Ví dụ ử lý thanh toán với PayPal, mảng $data sẽ có dạng:
   * [
   *   'class_codes' => ['MATH101', 'PHYS101'],
   *   'payment_method' => 'paypal',
   *   'payment_transaction_id' => '123456'   // ID nhận được từ PayPal trả về
   * ]
   */
  public function post(array $data) {
    try {


      #region Bước 2: Kiểm tra đăng nhập xem người dùng có đăng nhập chưa
      if (!$this->currentUser->isAuthenticated()) {
        throw new HttpException(403, 'Bạn cần đăng nhập để thanh toán.');
      }
      #endregion

      #region Bước 3: Kiểm tra dữ liệu nhận được từ front-end xem có mã lớp không
      if (empty($data['class_codes']) || !is_array($data['class_codes'])) {
        throw new HttpException(400, 'Danh sách mã lớp học không được để trống và phải là mảng.');
      }
      #endregion

      $total_amount = 0;
      $class_info = [];

      foreach ($data['class_codes'] as $class_code) {

        #region Doan này kiem tra trong CSDL xem có lớp học không, không có thì báo lỗi
        $query = \Drupal::entityQuery('node')
          ->condition('type', 'class')
          ->condition('title', $class_code)
          ->accessCheck(TRUE)
          ->range(0, 1);

        $results = $query->execute();

        if (empty($results)) {
          throw new HttpException(404, 'Không tìm thấy lớp học: ' . $class_code);
        }
        #endregion

        #region Bước 4: Tải lên thông tin lớp học
        $class = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load(reset($results));
        #endregion

        #region Doạn này thì biến $class ở bước thứ 3 có chứa id của khóa học, dùng nó để truy vấn trong CSDL và lấy thông tin khóa học
        $course = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load($class->get('field_class_course_reference')->target_id);
        #endregion

        #region Bước 5: Kiểm tra xem người dùng đã đăng ký lớp học chưa, không tìm thấy thông tin trong CSDL hoặc có nhưng trạng thái để là confirmed thay vì pending thì báo lỗi

        // pending: tức là đã đăng ký nhưng chưa thanh toán.
        // confirmed: tức là đã đăng ký và đã thanh toán.
        $registration_query = \Drupal::entityQuery('node')
          ->condition('type', 'class_registration')
          ->condition('field_registration_class', $class->id())
          ->condition('field_registration_user', $this->currentUser->id())
          ->condition('field_registration_status', 'pending')
          ->accessCheck(TRUE);

        $registration_results = $registration_query->execute();
        if (empty($registration_results)) {
          throw new HttpException(400, 'Bạn chưa đăng ký hoặc đã thanh toán lớp học: ' . $class_code);
        }
        #endregion

        #region Bước 6: Dòng này là để tính tổng số tiền cần thanh toán

        // Cụ thể là ví dụ người dùng đăng ký 2 lớp học, mỗi lớp học thuộc khóa học có giá 1.000.000 VND
        // thì vòng lặp foreach này sẽ chạy 2 lần, mỗi lần lấy ra một lớp học và cộng vào biến $total_amount
        // tổng cộng biến $total_amount sẽ có giá trị là 2.000.000 VND.
        $total_amount += (float) $course->get('field_course_tuition_fee')->value;
        #endregion

        #region Doạn này là để lưu thông tin lớp học phục vụ cho chức năng Google Analytics nhưng hiện tại chưa làm được nên đoạn này tạm bỏ qua
        $class_info[] = [
          'class_id' => $class->id(),
          'class_code' => $class_code,
          'class_name' => $class->label(),
          'course_code' => $course->get('field_course_code')->value,
          'course_name' => $course->label(),
          'amount' => $course->get('field_course_tuition_fee')->value,
        ];
        #endregion
      }

      #region Doạn này kiểm tra xem front-end có gửi thông tin phương thức thanh toán không, không có thì báo lỗi
      if (empty($data['payment_method'])) {
        throw new HttpException(400, 'Phương thức thanh toán không được để trống.');
      }
      #endregion

      // Get receipt service
      /** @var \Drupal\course_register\Service\ReceiptService $receipt_service */
      $receipt_service = \Drupal::service('course_register.receipt');

      #region Bước 7: Kiểm tra phương thức thanh toán.
      switch ($data['payment_method']) {
        case 'vnpay':

          #region Doạn này là chuẩn bị thông tin đơn hàng để gửi sang VNPAY
          $order_info = [
            'amount' => $total_amount,                // Tổng số tiền cần thanh toán, xem lại bước 6.
            'class_codes' => $data['class_codes'],    // Danh sách mã lớp học, xem lại bước 3.
            'user_id' => $this->currentUser->id(),    // ID của người dùng đăng nhập.
          ];
          #endregion

          // Gọi hàm tạo URL thanh toán VNPAY.
          $payment_url = $this->createVnpayPaymentUrl($order_info);

          #region Doạn trả về response cho front-end thì quan tâm đến 'payment_url' là được rồi còn phần 'analytics_data' là để phục vụ cho chức năng Google Analytics nên hiện tại chưa cần quan tâm
          return new ResourceResponse([
            'payment_url' => $payment_url,
            'analytics_data' => [
              'classes' => $class_info,
              'total_amount' => $total_amount,
              'currency' => 'VND',
              'payment_method' => 'vnpay',
              'student_id' => $this->currentUser->id(),
              'student_name' => $this->currentUser->getDisplayName(),
            ],
          ]);
          #endregion

        case 'paypal':
          if (empty($data['payment_transaction_id'])) {
            throw new HttpException(400, 'Thiếu thông tin giao dịch PayPal.');
          }

          // Verify PayPal transaction
          //if (!$this->verifyPaypalTransaction($data['payment_transaction_id'])) {
          //  throw new HttpException(400, 'Giao dịch PayPal không hợp lệ.');
          //}

          $class_ids = [];
          $transactions = [];
          $amount_per_class = $total_amount / count($class_info);

          // Cập nhật trạng thái đăng ký và tạo transaction history cho mỗi lớp
          foreach ($class_info as $info) {
            // Cập nhật trạng thái đăng ký
            $registration_query = \Drupal::entityQuery('node')
              ->condition('type', 'class_registration')
              ->condition('field_registration_class', $info['class_id'])
              ->condition('field_registration_user', $this->currentUser->id())
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

            // Tạo transaction history
            $transaction = \Drupal::entityTypeManager()
              ->getStorage('node')
              ->create([
                'type' => 'transaction_history',
                'title' => sprintf('[PayPal] %s - %s',
                  $info['class_name'],
                  $this->currentUser->getDisplayName()
                ),
                'field_transaction_course' => ['target_id' => $info['class_id']],
                'field_transaction_date' => date('Y-m-d\TH:i:s'),
                'field_transaction_id' => $data['payment_transaction_id'],
                'field_transaction_method' => 'paypal',
                'field_transaction_user' => ['target_id' => $this->currentUser->id()],
                'field_transaction_amount' => $amount_per_class,
                'status' => 1,
              ]);

            $transaction->save();

            $class_ids[] = $info['class_id'];

            $transactions[] = [
              'transaction_id' => $data['payment_transaction_id'],
              'class_code' => $info['class_code'],
              'class_name' => $info['class_name'],
              'amount' => $amount_per_class,
            ];
          }

          // Tạo một receipt cho tất cả các lớp
          $receipt_data = [
            'amount' => $total_amount,
            'payment_method' => 'paypal',
            'transaction_id' => $data['payment_transaction_id'],
            'user_id' => $this->currentUser->id(),
            'class_ids' => $class_ids,
          ];

          $receipt = $receipt_service->createReceipt($receipt_data);

          return new ResourceResponse([
            'message' => 'Thanh toán thành công',
            'data' => [
              'transaction_id' => $data['payment_transaction_id'],
              'payment_method' => 'paypal',
              'payment_date' => date('Y-m-d H:i:s'),
              'total_amount' => $total_amount,
              'transactions' => $transactions,
              'receipt_id' => $receipt->id(),
            ],
            'analytics_data' => [
              'transaction_id' => $data['payment_transaction_id'],
              'total_amount' => $total_amount,
              'currency' => 'VND',
              'payment_method' => 'paypal',
              'classes' => $class_info,
              'payment_date' => date('Y-m-d H:i:s'),
              'student_id' => $this->currentUser->id(),
              'student_name' => $this->currentUser->getDisplayName(),
            ],
          ], 201);

        default:
          throw new HttpException(400, 'Phương thức thanh toán không hợp lệ.');
      }
      #endregion
    }
    catch (HttpException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $this->logger->error('Lỗi khi thanh toán: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw new HttpException(500, 'Có lỗi xảy ra khi thanh toán. Vui lòng thử lại sau.');
    }
  }

}
