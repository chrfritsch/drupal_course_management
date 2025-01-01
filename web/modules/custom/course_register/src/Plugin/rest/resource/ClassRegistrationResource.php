<?php

namespace Drupal\course_register\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Provides a resource to register for classes.
 *
 * @RestResource(
 *   id = "class_registration",
 *   label = @Translation("Class registration"),
 *   uri_paths = {
 *     "create" = "/api/v1/class-registration"
 *   }
 * )
 */
class ClassRegistrationResource extends ResourceBase {

  protected $currentUser;

  protected $entityTypeManager;

  protected $mailManager;

  protected $dateFormatter;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->dateFormatter = $date_formatter;
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
      $container->get('logger.factory')->get('course_register'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('date.formatter')
    );
  }

  /**
   * Responds to POST requests.
   */
  public function post($data) {
    try {
      // Validate required data.
      if (empty($data['class_id'])) {
        throw new \Exception(('Class ID is required'));
      }

      // Load class node.
      $class = $this->entityTypeManager->getStorage('node')
        ->load($data['class_id']);
      if (!$class || $class->bundle() !== 'class') {
        throw new \Exception('Invalid class');
      }

      // Kiểm tra trạng thái lớp học
      if ($class->get('field_class_status')->value !== 'active') {
        throw new \Exception('Lớp học này đã kết thúc hoặc không còn nhận đăng ký');
      }

      // Load course node to get payment info
      $course = $this->entityTypeManager->getStorage('node')
        ->load($class->get('field_class_course_reference')->target_id);
      if (!$course) {
        throw new \Exception('Invalid course');
      }

      // Handle registration based on user status.
      if ($this->currentUser->isAuthenticated()) {
        $this->handleAuthenticatedRegistration($class, $course);
      }
      else {
        if (empty($data['user_info'])) {
          throw new \Exception('User information is required for anonymous registration');
        }
        $this->handleAnonymousRegistration($class, $course, $data['user_info']);
      }

      return new ResourceResponse(['message' => 'Registration successful']);
    }
    catch (\Exception $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 400);
    }
  }

  /**
   * Check if user already registered for the class.
   */
  protected function checkExistingRegistration($user_id, $class_id) {
    // Kiểm tra trong bảng node class_registration
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'class_registration')
      ->condition('field_registration_class', $class_id)
      ->condition('field_registration_user', $user_id)
      ->accessCheck(FALSE);

    $results = $query->execute();

    if (!empty($results)) {
      throw new \Exception('Bạn đã đăng ký lớp học này rồi');
    }
  }

  /**
   * Handle registration for authenticated users.
   */
  protected function handleAuthenticatedRegistration($class, $course) {
    // Check if user already registered for this class
    $this->checkExistingRegistration($this->currentUser->id(), $class->id());

    // Get current time and payment deadline
    $current_time = new \DateTime();
    $payment_deadline = new \DateTime('+7 days');

    // Create registration node
    $registration = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'class_registration',
      'title' => '[Đăng ký] ' . $this->currentUser->getAccountName() . ' - ' . $class->getTitle(),
      'field_registration_class' => ['target_id' => $class->id()],
      'field_registration_user' => ['target_id' => $this->currentUser->id()],
      'field_registration_date' => [
        'value' => $current_time->format('Y-m-d\TH:i:s'),
      ],
      'field_registration_status' => 'pending',
      'field_payment_deadline' => [
        'value' => $payment_deadline->format('Y-m-d\TH:i:s'),
      ],
    ]);
    $registration->save();

    // Add user to class's user list
    $current_user_list = $class->get('field_user_list')->getValue();
    $current_user_list[] = [
      'target_id' => $this->currentUser->id(),
    ];
    $class->set('field_user_list', $current_user_list);
    $class->save();

    // Get user email
    $user = $this->entityTypeManager->getStorage('user')
      ->load($this->currentUser->id());

    // Send confirmation email
    $this->sendConfirmationEmail(
      $user->getEmail(),
      $class,
      $user->get('field_fullname')->value ?? $user->getDisplayName(),
      $course->get('field_course_tuition_fee')->value,
      $registration->id()
    );
  }

  /**
   * Handle registration for anonymous users.
   */
  protected function handleAnonymousRegistration($class, $course, $user_info) {
    // Validate user info
    if (empty($user_info['email']) || empty($user_info['fullname']) || empty($user_info['phone'])) {
      throw new \Exception('Email, full name and phone are required');
    }

    // Validate email format
    if (!filter_var($user_info['email'], FILTER_VALIDATE_EMAIL)) {
      throw new \Exception('Invalid email format');
    }

    // Check if email already exists
    $existing_users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['mail' => $user_info['email']]);
    if (!empty($existing_users)) {
      throw new \Exception('Email address is already registered');
    }

    // Check if username already exists
    $existing_users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => $user_info['email']]);
    if (!empty($existing_users)) {
      throw new \Exception('Username is already taken');
    }

    // Generate random password
    $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

    // Create new user account
    $user = $this->entityTypeManager->getStorage('user')->create([
      'name' => $user_info['username'],
      'mail' => $user_info['email'],
      'field_fullname' => $user_info['fullname'],
      'field_phone_number' => $user_info['phone'],
      'field_identification_code' => $user_info['identification_code'],
      'field_workplace' => $user_info['workplace'],
      'status' => 1,
      'pass' => $password,
    ]);
    $user->addRole('student');
    $user->save();

    // Check if user already registered for this class
    $this->checkExistingRegistration($user->id(), $class->id());

    // Get current time and payment deadline
    $current_time = new \DateTime();
    $payment_deadline = new \DateTime('+7 days');

    // Create registration node
    $registration = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'class_registration',
      'title' => '[Đăng ký] ' . $user_info['username'] . ' - ' . $class->getTitle(),
      'field_registration_class' => ['target_id' => $class->id()],
      'field_registration_user' => ['target_id' => $user->id()],
      'field_registration_date' => [
        'value' => $current_time->format('Y-m-d\TH:i:s'),
      ],
      'field_registration_status' => 'pending',
      'field_payment_deadline' => [
        'value' => $payment_deadline->format('Y-m-d\TH:i:s'),
      ],
    ]);
    $registration->save();

    // Add user to class's user list
    $current_user_list = $class->get('field_user_list')->getValue();
    $current_user_list[] = ['target_id' => $user->id()];
    $class->set('field_user_list', $current_user_list);
    $class->save();

    // Send confirmation email
    $this->sendConfirmationEmail(
      $user_info['email'],
      $class,
      $user_info['fullname'],
      $course->get('field_course_tuition_fee')->value,
      $registration->id(),
      $user_info['username'],
      $password
    );
  }

  /**
   * Send confirmation email.
   */
  protected function sendConfirmationEmail($to, $class, $user_name, $price, $registration_id, $username = NULL, $password = NULL) {
    $course = $class->get('field_class_course_reference')->entity;
    $teacher = $class->get('field_class_teacher')->entity;

    // Lấy timestamp từ các field Date
    $open_date = strtotime($class->get('field_class_open_date')->value);
    $end_date = strtotime($class->get('field_class_end_date')->value);

    $params = [
      'class_title' => $class->getTitle(),
      'user_name' => $user_name,
      'course_title' => $course->getTitle(),
      'price' => $price,
      'deadline' => $this->dateFormatter->format(strtotime('+7 days'), 'custom', 'd/m/Y'),
      'payment_url' => '/payment/' . $registration_id,
      'class_open_date' => $this->dateFormatter->format($open_date, 'custom', 'd/m/Y'),
      'class_end_date' => $this->dateFormatter->format($end_date, 'custom', 'd/m/Y'),
      'teacher_name' => $teacher ? $teacher->get('field_fullname')->value : 'Chưa phân công',
      'room' => $class->get('field_room')->value,
    ];

    // Chỉ thêm thông tin đăng nhập cho anonymous user
    if ($username && $password) {
      $params['username'] = $username;
      $params['password'] = $password;
    }

    $this->mailManager->mail(
      'course_register',
      'registration_confirmation',
      $to,
      'vi',
      $params
    );
  }

}
