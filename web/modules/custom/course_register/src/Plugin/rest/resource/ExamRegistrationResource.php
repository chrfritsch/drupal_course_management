<?php

namespace Drupal\course_register\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;

/**
 * @RestResource(
 *   id = "exam_registration",
 *   label = @Translation("Exam Registration"),
 *   uri_paths = {
 *     "create" = "/api/v1/exam-registration",
 *     "canonical" = "/api/v1/exam-registration/{id}"
 *   },
 *   authentication_providers = {
 *     "basic_auth"
 *   }
 * )
 */
class ExamRegistrationResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The password generator.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passwordGenerator;

  /**
   * Constructs a new ExamRegistrationResource object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    LoggerInterface $logger,
    PasswordGeneratorInterface $password_generator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->passwordGenerator = $password_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory')->get('course_register'),
      $container->get('password_generator')
    );
  }

  /**
   * Responds to POST requests.
   */
  public function post($data) {
    try {
      // Debug
      $request = \Drupal::request();
      \Drupal::logger('exam_registration')->notice('Auth header: @auth', [
        '@auth' => $request->headers->get('Authorization')
      ]);
      \Drupal::logger('exam_registration')->notice('User: @user', [
        '@user' => $this->currentUser->id()
      ]);

      // Validate required data
      if (empty($data['exam_id']) || empty($data['user_info'])) {
        throw new HttpException(400, 'Thiếu thông tin bắt buộc');
      }

      // Validate user info
      $this->validateUserInfo($data['user_info']);

      // Load exam node
      $exam = $this->entityTypeManager->getStorage('node')
        ->load($data['exam_id']);

      if (!$exam || $exam->bundle() !== 'exam_schedule') {
        throw new HttpException(404, 'Không tìm thấy kỳ thi');
      }

      // Validate exam status and capacity
      $this->validateExam($exam);

      // Handle registration based on user status
      if ($this->currentUser->isAuthenticated()) {
        $registration = $this->handleAuthenticatedRegistration($exam, $data['user_info']);
      }
      else {
        $registration = $this->handleAnonymousRegistration($exam, $data['user_info']);
      }

      // Send confirmation email
      $this->sendConfirmationEmail($registration);

      return new ResourceResponse([
        'message' => 'Đăng ký thành công',
        'registration_id' => $registration->id(),
        'payment_url' => '/payment/exam/' . $registration->id(),
      ], 201);
    }
    catch (\Exception $e) {
      throw new HttpException(500, $e->getMessage());
    }
  }

  /**
   * Validate user information.
   */
  protected function validateUserInfo($user_info) {
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

    // Validate email format
    if (!filter_var($user_info['email'], FILTER_VALIDATE_EMAIL)) {
      throw new HttpException(400, 'Email không hợp lệ');
    }

    // Validate phone format - chấp nhận cả định dạng quốc tế
    $phone = $user_info['phone'];
    // Xóa dấu + ở đầu nếu có
    if (strpos($phone, '+') === 0) {
      $phone = substr($phone, 1);
    }
    // Xóa mã quốc gia 84 nếu có
    if (strpos($phone, '84') === 0) {
      $phone = substr($phone, 2);
    }
    // Kiểm tra số điện thoại còn lại phải là 9 hoặc 10 số
    if (!preg_match('/^[0-9]{9,10}$/', $phone)) {
      throw new HttpException(400, 'Số điện thoại không hợp lệ');
    }

    // Format lại số điện thoại trước khi lưu
    $user_info['phone'] = '+84' . ltrim($phone, '0');
  }

  /**
   * Validate exam status and capacity.
   */
  protected function validateExam($exam) {
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
   * Handle registration for authenticated user.
   */
  protected function handleAuthenticatedRegistration($exam, $user_info) {
    try {
      // Format phone number
      $phone = $user_info['phone'];
      if (strpos($phone, '+') === 0) {
        $phone = substr($phone, 1);
      }
      if (strpos($phone, '84') === 0) {
        $phone = substr($phone, 2);
      }
      $phone = '+84' . ltrim($phone, '0');

      // Tìm user dựa vào email
      $users = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['mail' => $user_info['email']]);
      $user = reset($users);

      if ($user) {
        // Cập nhật thông tin user
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

      // Create exam registration node
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
   * Handle registration for anonymous user.
   */
  protected function handleAnonymousRegistration($exam, $user_info) {
    // Generate password.
    $password = $this->passwordGenerator->generate(8);

    // Convert fullname to username
    $username = $this->convertFullnameToUsername($user_info['fullname']);

    // Create new user account
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
      'field_workplace' => $user_info['workplace'] ?? '', // Optional field
    ]);
    $user->addRole('student');
    $user->save();

    // Create exam registration node trực tiếp thay vì gọi handleAuthenticatedRegistration
    $registration = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'exam_registration',
      'title' => $exam->getTitle() . ' - ' . $user_info['fullname'],
      'field_exam_reference' => ['target_id' => $exam->id()],
      'field_registration_exam_user' => ['target_id' => $user->id()],
      // Dùng ID của user mới
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

    // Lưu thông tin tạm thời vào registration
    $registration->temporary_account_info = [
      'username' => $username,  // Dùng username đã convert
      'password' => $password,
    ];

    return $registration;
  }

  /**
   * Send confirmation email.
   */
  protected function sendConfirmationEmail($registration) {
    $exam = $registration->get('field_exam_reference')->entity;

    // Format thời gian từ seconds sang H:i
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

    // Add login credentials for new users
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
   * Get registered candidates count.
   */
  protected function getRegisteredCount($exam_id) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'exam_registration')
      ->condition('field_exam_reference', $exam_id)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->count();

    return $query->execute();
  }

  /**
   * Convert fullname to username.
   */
  protected function convertFullnameToUsername($fullname) {
    // Chuyển về chữ thường và bỏ dấu
    $username = mb_strtolower($fullname, 'UTF-8');
    $username = $this->removeAccents($username);

    // Thay thế khoảng trắng bằng không gì cả
    $username = preg_replace('/\s+/', '', $username);

    // Chỉ giữ lại chữ cái và số
    $username = preg_replace('/[^a-z0-9]/', '', $username);

    return $username;
  }

  /**
   * Remove accents from string.
   */
  protected function removeAccents($string) {
    $search = [
      'à',
      'á',
      'ạ',
      'ả',
      'ã',
      'â',
      'ầ',
      'ấ',
      'ậ',
      'ẩ',
      'ẫ',
      'ă',
      'ằ',
      'ắ',
      'ặ',
      'ẳ',
      'ẵ',
      'è',
      'é',
      'ẹ',
      'ẻ',
      'ẽ',
      'ê',
      'ề',
      'ế',
      'ệ',
      'ể',
      'ễ',
      'ì',
      'í',
      'ị',
      'ỉ',
      'ĩ',
      'ò',
      'ó',
      'ọ',
      'ỏ',
      'õ',
      'ô',
      'ồ',
      'ố',
      'ộ',
      'ổ',
      'ỗ',
      'ơ',
      'ờ',
      'ớ',
      'ợ',
      'ở',
      'ỡ',
      'ù',
      'ú',
      'ụ',
      'ủ',
      'ũ',
      'ư',
      'ừ',
      'ứ',
      'ự',
      'ử',
      'ữ',
      'ỳ',
      'ý',
      'ỵ',
      'ỷ',
      'ỹ',
      'đ',
      'À',
      'Á',
      'Ạ',
      'Ả',
      'Ã',
      'Â',
      'Ầ',
      'Ấ',
      'Ậ',
      'Ẩ',
      'Ẫ',
      'Ă',
      'Ằ',
      'Ắ',
      'Ặ',
      'Ẳ',
      'Ẵ',
      'È',
      'É',
      'Ẹ',
      'Ẻ',
      'Ẽ',
      'Ê',
      'Ề',
      'Ế',
      'Ệ',
      'Ể',
      'Ễ',
      'Ì',
      'Í',
      'Ị',
      'Ỉ',
      'Ĩ',
      'Ò',
      'Ó',
      'Ọ',
      'Ỏ',
      'Õ',
      'Ô',
      'Ồ',
      'Ố',
      'Ộ',
      'Ổ',
      'Ỗ',
      'Ơ',
      'Ờ',
      'Ớ',
      'Ợ',
      'Ở',
      'Ỡ',
      'Ù',
      'Ú',
      'Ụ',
      'Ủ',
      'Ũ',
      'Ư',
      'Ừ',
      'Ứ',
      'Ự',
      'Ử',
      'Ữ',
      'Ỳ',
      'Ý',
      'Ỵ',
      'Ỷ',
      'Ỹ',
      'Đ',
    ];
    $replace = [
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'i',
      'i',
      'i',
      'i',
      'i',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'y',
      'y',
      'y',
      'y',
      'y',
      'd',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'a',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'e',
      'i',
      'i',
      'i',
      'i',
      'i',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'o',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'u',
      'y',
      'y',
      'y',
      'y',
      'y',
      'd',
    ];
    return str_replace($search, $replace, $string);
  }

}
