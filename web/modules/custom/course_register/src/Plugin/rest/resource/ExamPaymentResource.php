<?php

declare(strict_types=1);

namespace Drupal\course_register\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Provides Exam Payment Resource.
 *
 * @RestResource(
 *   id = "exam_payment",
 *   label = @Translation("Exam Payment"),
 *   uri_paths = {
 *     "create" = "/api/v1/exam-payment"
 *   }
 * )
 */
final class ExamPaymentResource extends ResourceBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The mail manager.
   */
  protected MailManagerInterface $mailManager;

  /**
   * VNPAY config.
   */
  private array $vnpayConfig;

  /**
   * Constructs a new ExamPaymentResource object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    MailManagerInterface $mail_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->mailManager = $mail_manager;
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
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   The request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function post(array $data): ResourceResponse {
    try {
      // Validate input
      if (empty($data['registration_ids']) || !is_array($data['registration_ids'])) {
        throw new HttpException(400, 'Danh sách đăng ký không được để trống và phải là mảng.');
      }
      if (empty($data['payment_method'])) {
        throw new HttpException(400, 'Phương thức thanh toán không được để trống.');
      }

      // Kiểm tra đăng nhập
      if (!$this->currentUser->isAuthenticated()) {
        throw new HttpException(403, 'Bạn cần đăng nhập để thanh toán.');
      }

      // Load tất cả registrations và tính tổng tiền
      $total_amount = 0;
      $exam_info = [];
      $registrations = [];

      foreach ($data['registration_ids'] as $registration_id) {
        $registration = $this->entityTypeManager
          ->getStorage('node')
          ->load($registration_id);

        if (!$registration || $registration->bundle() !== 'exam_registration') {
          throw new HttpException(404, 'Không tìm thấy thông tin đăng ký: ' . $registration_id);
        }

        // Kiểm tra quyền sở hữu registration
        if ($registration->get('field_registration_exam_user')->target_id != $this->currentUser->id()) {
          throw new HttpException(403, 'Bạn không có quyền thanh toán đăng ký này.');
        }

        // Kiểm tra trạng thái đăng ký
        $registration_status = $registration->get('field_registration_exam_status')->value;
        if ($registration_status !== 'pending') {
          throw new HttpException(400, 'Đăng ký này không ở trạng thái chờ thanh toán.');
        }

        // Load thông tin kỳ thi
        $exam = $registration->get('field_exam_reference')->entity;
        if (!$exam) {
          throw new HttpException(404, 'Không tìm thấy thông tin kỳ thi.');
        }

        // Kiểm tra deadline thanh toán
        $payment_deadline = $registration->get('field_payment_exam_deadline')->value;
        if (strtotime($payment_deadline) < time()) {
          throw new HttpException(400, 'Đã quá hạn thanh toán cho đăng ký này.');
        }

        $exam_fee = $exam->get('field_exam_fee')->value;
        if (empty($exam_fee)) {
          throw new HttpException(400, 'Không tìm thấy thông tin lệ phí thi.');
        }

        $total_amount += (float)$exam_fee;
        $exam_info[] = [
          'exam_id' => $exam->id(),
          'exam_name' => $exam->label(),
          'exam_date' => $exam->get('field_exam_date')->value,
          'amount' => $exam_fee,
          'registration_date' => $registration->get('field_registration_exam_date')->value,
          'participant_info' => [
            'fullname' => $registration->get('field_participant_fullname')->value,
            'email' => $registration->get('field_participant_email')->value,
            'phone' => $registration->get('field_participant_phone')->value,
            'birthday' => $registration->get('field_participant_birthday')->value,
            'gender' => $registration->get('field_participant_gender')->value,
            'identification' => $registration->get('field_participant_identification')->value,
            'permanent_address' => $registration->get('field_permanent_address')->value,
            'temporary_address' => $registration->get('field_temporary_address')->value,
          ]
        ];
        $registrations[] = $registration;
      }

      // Xử lý theo phương thức thanh toán
      switch ($data['payment_method']) {
        case 'vnpay':
          // Kiểm tra config VNPAY
          if (empty($this->vnpayConfig) ||
              empty($this->vnpayConfig['tmn_code']) ||
              empty($this->vnpayConfig['hash_secret']) ||
              empty($this->vnpayConfig['payment_url'])) {
            throw new HttpException(500, 'Thiếu cấu hình VNPAY.');
          }

          $order_info = [
            'amount' => $total_amount,
            'registration_ids' => $data['registration_ids'],
            'user_id' => $this->currentUser->id(),
          ];

          try {
            $payment_url = $this->createVnpayPaymentUrl($order_info);
            return new ResourceResponse([
              'payment_url' => $payment_url,
              'analytics_data' => [
                'exams' => $exam_info,
                'total_amount' => $total_amount,
                'currency' => 'VND',
                'payment_method' => 'vnpay',
                'student_id' => $this->currentUser->id(),
                'student_name' => $this->currentUser->getDisplayName(),
              ],
            ]);
          }
          catch (\Exception $e) {
            $this->logger->error('Lỗi tạo URL VNPAY: @error', [
              '@error' => $e->getMessage()
            ]);
            throw new HttpException(500, 'Không thể tạo URL thanh toán VNPAY.');
          }

        case 'paypal':
          if (empty($data['transaction_id'])) {
            throw new HttpException(400, 'Thiếu transaction ID.');
          }

          // Cập nhật trạng thái các registrations
          foreach ($registrations as $registration) {
            $registration->set('field_registration_exam_status', 'confirmed');
            $registration->save();

            // Gửi email thông báo
            $this->sendPaymentConfirmationEmail($registration);
          }

          return new ResourceResponse([
            'status' => 'success',
            'message' => 'Thanh toán thành công',
            'analytics_data' => [
              'transaction_id' => $data['transaction_id'],
              'total_amount' => $total_amount,
              'currency' => 'VND',
              'payment_method' => 'paypal',
              'exams' => $exam_info,
              'payment_date' => date('Y-m-d H:i:s'),
              'student_id' => $this->currentUser->id(),
              'student_name' => $this->currentUser->getDisplayName(),
            ],
          ]);

        default:
          throw new HttpException(400, 'Phương thức thanh toán không hợp lệ.');
      }
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

  /**
   * Tạo URL thanh toán VNPAY.
   *
   * @param array $order_info
   *   Thông tin đơn hàng.
   *
   * @return string
   *   URL thanh toán VNPAY.
   */
  private function createVnpayPaymentUrl(array $order_info): string {
    $vnp_TxnRef = implode(',', $order_info['registration_ids']) . '_' . time();
    $vnp_OrderInfo = json_encode($order_info);
    $vnp_Amount = (int) ($order_info['amount'] * 100);

    $inputData = [
      "vnp_Version" => "2.1.0",
      "vnp_TmnCode" => $this->vnpayConfig['tmn_code'],
      "vnp_Amount" => (string) $vnp_Amount,
      "vnp_Command" => "pay",
      "vnp_CreateDate" => date('YmdHis'),
      "vnp_CurrCode" => "VND",
      "vnp_IpAddr" => \Drupal::request()->getClientIp(),
      "vnp_Locale" => "vn",
      "vnp_OrderInfo" => $vnp_OrderInfo,
      "vnp_OrderType" => "exam_fee",
      "vnp_ReturnUrl" => \Drupal::request()->getSchemeAndHttpHost() . '/payment/exam-vnpay-return',
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
   * Gửi email xác nhận thanh toán.
   *
   * @param \Drupal\node\Entity\Node $registration
   *   Node đăng ký thi.
   */
  private function sendPaymentConfirmationEmail($registration): void {
    $exam = $registration->get('field_exam_reference')->entity;
    $to = $registration->get('field_participant_email')->value;
    $params = [
      'registration' => $registration,
      'exam' => $exam,
      'user' => $this->currentUser,
    ];

    $this->mailManager->mail(
      'course_register',
      'exam_payment_confirmation',
      $to,
      'vi',
      $params
    );
  }

}
