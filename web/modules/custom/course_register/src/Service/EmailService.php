<?php

namespace Drupal\course_register\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for handling emails.
 */
class EmailService {
  use StringTranslationTrait;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new EmailService.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Send exam registration confirmation email.
   */
  public function sendExamRegistrationConfirmation($registration) {
    $exam = $registration->get('field_exam_reference')->entity;
    $user = $registration->get('field_registration_user')->entity;
    $site_config = $this->configFactory->get('system.site');

    $params = [
      'subject' => $this->t('Xác nhận đăng ký kỳ thi @exam_name', [
        '@exam_name' => $exam->get('field_exam_name')->value,
      ]),
      'exam_info' => [
        'name' => $exam->get('field_exam_name')->value,
        'date' => $exam->get('field_exam_date')->value,
        'start_time' => $exam->get('field_exam_start_time')->value,
        'end_time' => $exam->get('field_exam_end_time')->value,
        'location' => $exam->get('field_exam_location')->value,
        'fee' => number_format($exam->get('field_exam_fee')->value, 0, ',', '.') . ' VNĐ',
      ],
      'candidate_info' => [
        'name' => $registration->get('field_fullname')->value,
        'identification' => $registration->get('field_identification')->value,
        'birthday' => $registration->get('field_birthday')->value,
        'gender' => $this->getGenderLabel($registration->get('field_gender')->value),
        'phone' => $registration->get('field_phone')->value,
        'email' => $registration->get('field_email')->value,
      ],
      'registration_info' => [
        'id' => $registration->id(),
        'date' => $registration->get('field_registration_date')->value,
        'payment_deadline' => $registration->get('field_payment_deadline')->value,
        'payment_url' => $this->getPaymentUrl($registration->id()),
      ],
      'site_name' => $site_config->get('name'),
      'site_url' => $GLOBALS['base_url'],
    ];

    // Add login credentials for new users.
    if (isset($user->plain_password)) {
      $params['account_info'] = [
        'username' => $user->getAccountName(),
        'password' => $user->plain_password,
      ];
    }

    return $this->mailManager->mail(
      'course_register',
      'exam_registration_confirmation',
      $registration->get('field_email')->value,
      $this->languageManager->getDefaultLanguage()->getId(),
      $params
    );
  }

  /**
   * Send payment confirmation email.
   */
  public function sendPaymentConfirmation($registration, $transaction) {
    $exam = $registration->get('field_exam_reference')->entity;
    $site_config = $this->configFactory->get('system.site');

    $params = [
      'subject' => $this->t('Xác nhận thanh toán lệ phí thi @exam_name', [
        '@exam_name' => $exam->get('field_exam_name')->value,
      ]),
      'exam_info' => [
        'name' => $exam->get('field_exam_name')->value,
        'date' => $exam->get('field_exam_date')->value,
        'start_time' => $exam->get('field_exam_start_time')->value,
        'end_time' => $exam->get('field_exam_end_time')->value,
        'location' => $exam->get('field_exam_location')->value,
      ],
      'payment_info' => [
        'amount' => number_format($transaction->get('field_transaction_amount')->value, 0, ',', '.') . ' VNĐ',
        'method' => $this->getPaymentMethodLabel($transaction->get('field_transaction_method')->value),
        'transaction_id' => $transaction->get('field_transaction_id')->value,
        'date' => $transaction->get('field_transaction_date')->value,
      ],
      'candidate_info' => [
        'name' => $registration->get('field_fullname')->value,
        'identification' => $registration->get('field_identification')->value,
      ],
      'site_name' => $site_config->get('name'),
      'site_url' => $GLOBALS['base_url'],
    ];

    return $this->mailManager->mail(
      'course_register',
      'exam_payment_confirmation',
      $registration->get('field_email')->value,
      $this->languageManager->getDefaultLanguage()->getId(),
      $params
    );
  }

  /**
   * Get gender label.
   */
  private function getGenderLabel($value) {
    $labels = [
      'male' => 'Nam',
      'female' => 'Nữ',
      'other' => 'Khác',
    ];
    return $labels[$value] ?? $value;
  }

  /**
   * Get payment method label.
   */
  private function getPaymentMethodLabel($value) {
    $labels = [
      'vnpay' => 'VNPAY',
      'paypal' => 'PayPal',
    ];
    return $labels[$value] ?? $value;
  }

  /**
   * Get payment URL.
   */
  private function getPaymentUrl($registration_id) {
    return $GLOBALS['base_url'] . '/payment/exam/' . $registration_id;
  }

}
