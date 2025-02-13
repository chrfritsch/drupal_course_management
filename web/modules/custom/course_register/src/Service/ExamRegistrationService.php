<?php

namespace Drupal\course_register\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\course_register\Interface\ExamRegistrationServiceInterface;

/**
 *
 */
class ExamRegistrationService implements ExamRegistrationServiceInterface {

  protected $entityTypeManager;
  protected $currentUser;
  protected $mailManager;
  protected $passwordGenerator;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    MailManagerInterface $mail_manager,
    PasswordGeneratorInterface $password_generator,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->mailManager = $mail_manager;
    $this->passwordGenerator = $password_generator;
  }

  /**
   * Kiểm tra thông tin user.
   *
   * {@inheritdoc}
   */
  public function validateUserInfo($user_info) {
    $required_fields = [
      'fullname',
      'birthday',
      'gender',
      'identification',
      'permanent_address',
      'temporary_address',
      'phone',
      'email',
    ];

    foreach ($required_fields as $field) {
      if (empty($user_info[$field])) {
        throw new HttpException(400, "Thiếu thông tin: $field");
      }
    }

    // Validate email format.
    if (!filter_var($user_info['email'], FILTER_VALIDATE_EMAIL)) {
      throw new HttpException(400, 'Email không hợp lệ');
    }

    // Validate phone format - chấp nhận cả định dạng quốc tế.
    $phone = $user_info['phone'];
    // Xóa dấu + ở đầu nếu có.
    if (strpos($phone, '+') === 0) {
      $phone = substr($phone, 1);
    }
    // Xóa mã quốc gia 84 nếu có.
    if (strpos($phone, '84') === 0) {
      $phone = substr($phone, 2);
    }
    // Kiểm tra số điện thoại còn lại phải là 9 hoặc 10 số.
    if (!preg_match('/^[0-9]{9,10}$/', $phone)) {
      throw new HttpException(400, 'Số điện thoại không hợp lệ');
    }

    // Format lại số điện thoại trước khi lưu.
    $user_info['phone'] = '+84' . ltrim($phone, '0');
  }

  /**
   * Kiểm tra thông tin kỳ thi.
   *
   * {@inheritdoc}
   */
  public function validateExam($exam) {
    if ($exam->get('field_exam_status')->value !== 'sap_dien_ra') {
      throw new HttpException(400, 'Kỳ thi này không trong thời gian đăng ký');
    }

    $registered_count = $this->getRegisteredCount($exam->id());
    $max_candidates = $exam->get('field_max_candidates')->value;

    if ($registered_count >= $max_candidates) {
      throw new HttpException(400, 'Kỳ thi đã đủ số lượng thí sinh');
    }
  }

  /**
   * Xử lý đăng ký kỳ thi cho user đã đăng nhập.
   *
   * {@inheritdoc}
   */
  public function handleAuthenticatedRegistration($exam, $user_info) {
    try {
      // Format phone number.
      $phone = $user_info['phone'];
      if (strpos($phone, '+') === 0) {
        $phone = substr($phone, 1);
      }
      if (strpos($phone, '84') === 0) {
        $phone = substr($phone, 2);
      }
      $phone = '+84' . ltrim($phone, '0');

      // Tìm user dựa vào email.
      $users = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['mail' => $user_info['email']]);
      $user = reset($users);

      if ($user) {
        // Cập nhật thông tin user.
        $user->set('field_fullname', $user_info['fullname']);
        $user->set('field_identification_code', $user_info['identification']);
        $user->set('field_phone_number', $phone);
        $user->set('field_user_birthday', $user_info['birthday']);
        $user->set('field_user_gender', $user_info['gender']);
        $user->set('field_user_permanent_address', $user_info['permanent_address']);
        $user->set('field_user_temporary_address', $user_info['temporary_address']);
        if (!empty($user_info['workplace'])) {
          $user->set('field_workplace', $user_info['workplace']);
        }
        $user->save();

        \Drupal::logger('course_register')
          ->notice('Đã cập nhật thông tin user @uid với email @email', [
            '@uid' => $user->id(),
            '@email' => $user_info['email'],
          ]);
      }

      // Create exam registration node.
      $registration = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'exam_registration',
        'title' => $exam->getTitle() . ' - ' . $user_info['fullname'],
        'field_exam_reference' => ['target_id' => $exam->id()],
        'field_registration_exam_user' => ['target_id' => $this->currentUser->id()],
        'field_registration_exam_date' => date('Y-m-d\TH:i:s'),
        'field_payment_exam_deadline' => date('Y-m-d\TH:i:s', strtotime('+24 hours')),
        'field_participant_fullname' => $user_info['fullname'],
        'field_participant_birthday' => $user_info['birthday'],
        'field_participant_gender' => $user_info['gender'],
        'field_participant_identification' => $user_info['identification'],
        'field_permanent_address' => $user_info['permanent_address'],
        'field_temporary_address' => $user_info['temporary_address'],
        'field_participant_phone' => $phone,
        'field_participant_email' => $user_info['email'],
        'field_registration_exam_status' => 'pending',
        'status' => 1,
      ]);

      $registration->save();
      return $registration;
    }
    catch (\Exception $e) {
      \Drupal::logger('course_register')
        ->error('Lỗi khi xử lý đăng ký thi cho user có email @email: @error', [
          '@email' => $user_info['email'],
          '@error' => $e->getMessage(),
        ]);
      throw $e;
    }
  }

  /**
   * Xử lý đăng ký kỳ thi cho user chưa đăng nhập.
   *
   * {@inheritdoc}
   */
  public function handleAnonymousRegistration($exam, $user_info) {
    // Generate password.
    $password = $this->passwordGenerator->generate(8);

    // Convert fullname to username.
    $username = $this->convertFullnameToUsername($user_info['fullname']);

    // Create new user account.
    $user = $this->entityTypeManager->getStorage('user')->create([
      'name' => $username,
      'mail' => $user_info['email'],
      'pass' => $password,
      'status' => 1,
      'field_fullname' => $user_info['fullname'],
      'field_identification_code' => $user_info['identification'],
      'field_phone_number' => $user_info['phone'],
      'field_user_birthday' => $user_info['birthday'],
      'field_user_gender' => $user_info['gender'],
      'field_user_permanent_address' => $user_info['permanent_address'],
      'field_user_temporary_address' => $user_info['temporary_address'],
      'field_workplace' => $user_info['workplace'] ?? '',
    ]);
    $user->addRole('student');
    $user->save();

    // Create exam registration node.
    $registration = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'exam_registration',
      'title' => $exam->getTitle() . ' - ' . $user_info['fullname'],
      'field_exam_reference' => ['target_id' => $exam->id()],
      'field_registration_exam_user' => ['target_id' => $user->id()],
      'field_registration_exam_date' => date('Y-m-d\TH:i:s'),
      'field_payment_exam_deadline' => date('Y-m-d\TH:i:s', strtotime('+24 hours')),
      'field_participant_fullname' => $user_info['fullname'],
      'field_participant_birthday' => $user_info['birthday'],
      'field_participant_gender' => $user_info['gender'],
      'field_participant_identification' => $user_info['identification'],
      'field_permanent_address' => $user_info['permanent_address'],
      'field_temporary_address' => $user_info['temporary_address'],
      'field_participant_phone' => $user_info['phone'],
      'field_participant_email' => $user_info['email'],
      'field_registration_exam_status' => 'pending',
      'status' => 1,
    ]);
    $registration->save();

    // Lưu thông tin tạm thời vào registration.
    $registration->temporary_account_info = [
      'username' => $username,
      'password' => $password,
    ];

    return $registration;
  }

  /**
   * Gửi email xác nhận.
   *
   * {@inheritdoc}
   */
  public function sendConfirmationEmail($registration) {
    $exam = $registration->get('field_exam_reference')->entity;

    // Format thời gian từ seconds sang H:i.
    $start_time = date('H:i', $exam->get('field_exam_start_time')->value);
    $end_time = date('H:i', $exam->get('field_exam_end_time')->value);

    $params = [
      'exam_name' => $exam->get('title')->value,
      'exam_date' => $exam->get('field_exam_date')->value,
      'exam_start_time' => $start_time,
      'exam_end_time' => $end_time,
      'exam_location' => $exam->get('field_exam_location')->value,
      'exam_fee' => $exam->get('field_exam_fee')->value,
      'candidate_name' => $registration->get('field_participant_fullname')->value,
      'identification' => $registration->get('field_participant_identification')->value,
      'birthday' => $registration->get('field_participant_birthday')->value,
      'phone' => $registration->get('field_participant_phone')->value,
      'email' => $registration->get('field_participant_email')->value,
      'payment_deadline' => $registration->get('field_payment_exam_deadline')->value,
      'payment_url' => '/payment/exam/' . $registration->id(),
    ];

    // Add login credentials for new users.
    if (isset($registration->temporary_account_info)) {
      $params['username'] = $registration->temporary_account_info['username'];
      $params['password'] = $registration->temporary_account_info['password'];
    }

    $this->mailManager->mail(
      'course_register',
      'exam_registration_confirmation',
      $registration->get('field_participant_email')->value,
      'vi',
      $params
    );
  }

  /**
   * Lấy số lượng thí sinh đã đăng ký
   *
   * {@inheritdoc}
   */
  public function getRegisteredCount($exam_id) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'exam_registration')
      ->condition('field_exam_reference', $exam_id)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->count();

    return $query->execute();
  }

  /**
   * Chuyển đổi họ tên thành tên đăng nhập
   * Ví dụ: Nguyen Thi Thu Men => nguyenthithumen
   *
   * {@inheritdoc}
   */
  public function convertFullnameToUsername($fullname) {
    // Chuyển về chữ thường và bỏ dấu.
    $username = mb_strtolower($fullname, 'UTF-8');
    $username = $this->removeAccents($username);

    // Thay thế khoảng trắng bằng không gì cả.
    $username = preg_replace('/\s+/', '', $username);

    // Chỉ giữ lại chữ cái và số.
    $username = preg_replace('/[^a-z0-9]/', '', $username);

    return $username;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAccents($string) {
    $search = [
      'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ',
      'ặ', 'ẳ', 'ẵ', 'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
      'ì', 'í', 'ị', 'ỉ', 'ĩ', 'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ',
      'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ', 'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư',
      'ừ', 'ứ', 'ự', 'ử', 'ữ', 'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ', 'đ',
      'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ',
      'Ặ', 'Ẳ', 'Ẵ', 'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
      'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ', 'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ',
      'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ', 'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư',
      'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ', 'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ', 'Đ',
    ];
    $replace = [
      'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
      'a', 'a', 'a', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
      'i', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
      'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'u', 'u',
      'u', 'u', 'u', 'u', 'u', 'y', 'y', 'y', 'y', 'y', 'd',
      'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
      'a', 'a', 'a', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
      'i', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
      'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'u', 'u',
      'u', 'u', 'u', 'u', 'u', 'y', 'y', 'y', 'y', 'y', 'd',
    ];
    return str_replace($search, $replace, $string);
  }

}
