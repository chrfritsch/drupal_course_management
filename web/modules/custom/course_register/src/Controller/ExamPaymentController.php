<?php

declare(strict_types=1);

namespace Drupal\course_register\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for handling exam payment returns.
 */
class ExamPaymentController extends ControllerBase {

  /**
   * The mail manager.
   */
  protected MailManagerInterface $mailManager;

  /**
   * The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * VNPAY config.
   */
  private array $vnpayConfig;

  /**
   * Constructs a new ExamPaymentController.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->mailManager = $mail_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->vnpayConfig = \Drupal::service('settings')->get('vnpay_config');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Xử lý response từ VNPAY cho exam payment.
   */
  public function examVnpayReturn(Request $request) {
    try {
      // Verify response từ VNPAY.
      $vnpay_response = $request->query->all();
      if (empty($vnpay_response['vnp_ResponseCode'])) {
        throw new \Exception('Thiếu thông tin response từ VNPAY');
      }

      // Verify hash.
      if (!$this->verifyVnpayHash($vnpay_response)) {
        throw new \Exception('Sai chữ ký từ VNPAY');
      }

      if ($vnpay_response['vnp_ResponseCode'] === '00') {
        // Tách registration_ids từ vnp_TxnRef.
        $txn_ref_parts = explode('_', $vnpay_response['vnp_TxnRef']);
        $registration_ids = explode(',', $txn_ref_parts[0]);

        // Load tất cả registrations.
        $registrations = $this->entityTypeManager
          ->getStorage('node')
          ->loadMultiple($registration_ids);

        if (empty($registrations)) {
          throw new NotFoundHttpException('Không tìm thấy thông tin đăng ký.');
        }

        // Tạo mảng exam references và cập nhật trạng thái registrations.
        $exam_references = [];
        foreach ($registrations as $registration) {
          if ($registration->bundle() !== 'exam_registration') {
            throw new \Exception('ID không phải là đăng ký thi: ' . $registration->id());
          }

          $exam = $registration->get('field_exam_reference')->entity;
          if (!$exam) {
            throw new \Exception('Không tìm thấy thông tin kỳ thi cho đăng ký: ' . $registration->id());
          }

          $exam_references[] = [
            'target_id' => $exam->id(),
          ];

          // Cập nhật trạng thái registration.
          $registration->set('field_registration_exam_status', 'confirmed');
          $registration->save();
        }

        // Lấy user từ registration đầu tiên.
        $first_registration = reset($registrations);
        $user = $first_registration->get('field_registration_exam_user')->entity;
        if (!$user) {
          throw new \Exception('Không tìm thấy thông tin người dùng.');
        }

        // Tạo receipt.
        $receipt = Node::create([
          'type' => 'exam_payment_receipt',
          'title' => 'Exam Payment Receipt #' . time(),
          'field_exam_receipt_number' => $this->generateReceiptNumber(),
          'field_exam_receipt_date' => date('Y-m-d\TH:i:s'),
          'field_exam_receipt_amount' => $vnpay_response['vnp_Amount'] / 100,
          'field_exam_receipt_student' => ['target_id' => $user->id()],
          'field_exam_receipt_exams' => $exam_references,
          'field_exam_receipt_txn_id' => $vnpay_response['vnp_TransactionNo'],
          'field_exam_receipt_method' => 'vnpay',
          'field_exam_receipt_status' => 'completed',
        ]);
        $receipt->save();

        // Gửi email xác nhận.
        $this->sendPaymentConfirmationEmail($registrations, $receipt);

        // Tạo mảng transactions.
        $transactions = [];
        foreach ($registrations as $registration) {
          $exam = $registration->get('field_exam_reference')->entity;
          if (!$exam) {
            continue;
          }

          $transactions[] = [
            'transaction_id' => $vnpay_response['vnp_TransactionNo'],
          // 'exam_code' => $exam->get('field_exam_code')->value,
            'exam_name' => $exam->getTitle(),
            'amount' => $exam->get('field_exam_fee')->value,
            'receipt_id' => $receipt->id(),
          ];
        }

        $student_name = $user->get('field_fullname')->value;

        $response_data = [
          'status' => 'success',
          'code' => '00',
          'message' => 'Giao dịch thành công',
          'data' => [
            'vnp_ResponseCode' => $vnpay_response['vnp_ResponseCode'],
            'vnp_TransactionStatus' => $vnpay_response['vnp_ResponseCode'],
            'payment_date' => date('Y-m-d H:i:s', strtotime($receipt->get('field_exam_receipt_date')->value)),
            'total_amount' => $vnpay_response['vnp_Amount'] / 100,
            'transactions' => $transactions,
            'student_name' => $student_name,
            'receipt_id' => $receipt->id(),
          ],
        ];

        $query = http_build_query($response_data);
        return new TrustedRedirectResponse('http://localhost:5173/payment/exam-vnpay-return?' . $query);
      }

      // Xử lý khi giao dịch thất bại.
      $error_data = [
        'status' => 'error',
        'code' => $vnpay_response['vnp_ResponseCode'],
        'message' => 'Giao dịch thất bại',
        'data' => [
          'vnp_ResponseCode' => $vnpay_response['vnp_ResponseCode'],
          'vnp_TransactionStatus' => $vnpay_response['vnp_ResponseCode'],
          'payment_date' => date('Y-m-d H:i:s'),
        ],
      ];

      $query = http_build_query($error_data);
      return new TrustedRedirectResponse('http://localhost:5173/payment/exam-vnpay-return?' . $query);
    }
    catch (\Exception $e) {
      \Drupal::logger('exam_payment')->error('Lỗi xử lý VNPAY return: @error', [
        '@error' => $e->getMessage(),
      ]);

      $error_data = [
        'status' => 'error',
        'code' => '99',
        'message' => 'Đã xảy ra lỗi trong quá trình xử lý thanh toán',
        'data' => [
          'error' => $e->getMessage(),
          'payment_date' => date('Y-m-d H:i:s'),
        ],
      ];

      $query = http_build_query($error_data);
      return new TrustedRedirectResponse('http://localhost:5173/payment/exam-vnpay-return?' . $query);
    }
  }

  /**
   * Verify hash từ VNPAY.
   */
  private function verifyVnpayHash(array $vnpay_response): bool {
    if (empty($this->vnpayConfig['hash_secret'])) {
      throw new \Exception('Thiếu cấu hình VNPAY hash secret');
    }

    $vnp_SecureHash = $vnpay_response['vnp_SecureHash'] ?? '';
    unset($vnpay_response['vnp_SecureHash']);
    unset($vnpay_response['vnp_SecureHashType']);

    ksort($vnpay_response);
    $hashData = "";
    foreach ($vnpay_response as $key => $value) {
      if (!empty($value)) {
        $hashData .= $key . "=" . urlencode($value) . "&";
      }
    }
    $hashData = rtrim($hashData, "&");

    $secureHash = hash_hmac('sha512', $hashData, $this->vnpayConfig['hash_secret']);

    return $vnp_SecureHash === $secureHash;
  }

  /**
   * Generate unique receipt number.
   */
  private function generateReceiptNumber(): string {
    $prefix = 'EXM';
    $timestamp = date('YmdHis');
    $random = substr(str_shuffle('0123456789'), 0, 4);
    return $prefix . $timestamp . $random;
  }

  /**
   * Send payment confirmation email.
   */
  private function sendPaymentConfirmationEmail(array $registrations, Node $receipt): void {
    $exam_info = [];
    $first_exam = NULL;
    $first_registration = NULL;

    foreach ($registrations as $registration) {
      $exam = $registration->get('field_exam_reference')->entity;
      if (!$exam) {
        \Drupal::logger('exam_payment')->error('Không tìm thấy thông tin kỳ thi cho registration: @id', [
          '@id' => $registration->id(),
        ]);
        continue;
      }

      // Lưu exam và registration đầu tiên để dùng sau.
      if (!$first_exam) {
        $first_exam = $exam;
        $first_registration = $registration;
      }

      $exam_info[] = [
        'exam_name' => $exam->getTitle(),
        'exam_date' => $exam->get('field_exam_date')->value,
        'exam_fee' => $exam->get('field_exam_fee')->value,
        'participant_info' => [
          'fullname' => $registration->get('field_participant_fullname')->value,
          'email' => $registration->get('field_participant_email')->value,
          'phone' => $registration->get('field_participant_phone')->value,
        ],
      ];
    }

    // Kiểm tra nếu không có exam nào hợp lệ.
    if (!$first_exam || !$first_registration || empty($exam_info)) {
      \Drupal::logger('exam_payment')->error('Không có thông tin kỳ thi hợp lệ để gửi email');
      return;
    }

    $params = [
      'exam_name' => $first_exam->getTitle(),
      'exam_date' => $first_exam->get('field_exam_date')->value,
      'exam_start_time' => $first_exam->get('field_exam_start_time')->value,
      'exam_end_time' => $first_exam->get('field_exam_end_time')->value,
      'exam_location' => $first_exam->get('field_exam_location')->value,
      'exam_fee' => $first_exam->get('field_exam_fee')->value,
      'receipt_number' => $receipt->get('field_exam_receipt_number')->value,
      'amount' => $receipt->get('field_exam_receipt_amount')->value,
      'payment_method' => $receipt->get('field_exam_receipt_method')->value,
      'transaction_id' => $receipt->get('field_exam_receipt_txn_id')->value,
      'payment_date' => $receipt->get('field_exam_receipt_date')->value,
      'exam_info' => $exam_info,
      'receipt_pdf_url' => '/exam-receipt/' . $receipt->id() . '/pdf',
      // Thêm thông tin thí sinh.
      'candidate_name' => $first_registration->get('field_participant_fullname')->value,
      'identification' => $first_registration->get('field_participant_identification')->value,
      'birthday' => $first_registration->get('field_participant_birthday')->value,
      'phone' => $first_registration->get('field_participant_phone')->value,
      'email' => $first_registration->get('field_participant_email')->value,
    ];

    $student = $receipt->get('field_exam_receipt_student')->entity;
    if ($student) {
      $this->mailManager->mail(
        'course_register',
        'exam_payment_confirmation',
        $student->getEmail(),
        'vi',
        $params
      );
    }
  }

}
