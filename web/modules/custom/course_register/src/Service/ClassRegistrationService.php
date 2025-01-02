<?php

namespace Drupal\course_register\Service;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\course_register\Repository\ClassRegistrationRepository;

class ClassRegistrationService {
  protected $repository;
  protected $emailService;
  protected $currentUser;

  public function __construct(
    ClassRegistrationRepository $repository,
    EmailClassService $emailService,
    AccountProxyInterface $currentUser
  ) {
    $this->repository = $repository;
    $this->emailService = $emailService;
    $this->currentUser = $currentUser;
  }

  public function registerForClass($data) {
    if (empty($data['class_id'])) {
      throw new \Exception('Class ID is required');
    }

    $class = $this->repository->getClass($data['class_id']);
    if (!$class || $class->bundle() !== 'class') {
      throw new \Exception('Invalid class');
    }

    if ($class->get('field_class_status')->value !== 'active') {
      throw new \Exception('Lớp học này đã kết thúc hoặc không còn nhận đăng ký');
    }

    $course = $this->repository->getCourse($class->get('field_class_course_reference')->target_id);
    if (!$course) {
      throw new \Exception('Invalid course');
    }

    if ($this->currentUser->isAuthenticated()) {
      return $this->handleAuthenticatedRegistration($class, $course);
    }
    else {
      if (empty($data['user_info'])) {
        throw new \Exception('User information is required for anonymous registration');
      }
      return $this->handleAnonymousRegistration($class, $course, $data['user_info']);
    }
  }

  protected function handleAuthenticatedRegistration($class, $course) {
    // Check if user already registered for this class
    $existingRegistration = $this->repository->checkExistingRegistration($this->currentUser->id(), $class->id());
    if (!empty($existingRegistration)) {
      throw new \Exception('Bạn đã đăng ký lớp học này rồi');
    }

    // Get current time and payment deadline
    $current_time = new \DateTime();
    $payment_deadline = new \DateTime('+7 days');

    // Create registration node
    $registration = $this->repository->createRegistration([
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

    // Add user to class's user list
    $this->repository->updateClassUserList($class, $this->currentUser->id());

    // Get user email
    $user = $this->repository->getUser($this->currentUser->id());

    // Send confirmation email
    $this->emailService->sendRegistrationConfirmation(
      $user->getEmail(),
      $class,
      $user->get('field_fullname')->value ?? $user->getDisplayName(),
      $course->get('field_course_tuition_fee')->value,
      $registration->id()
    );

    return ['message' => 'Registration successful'];
  }

  protected function handleAnonymousRegistration($class, $course, $user_info) {
    // Validate user info
    $this->validateUserInfo($user_info);

    // Check if email already exists
    $existing_users = $this->repository->getUserByEmail($user_info['email']);
    if (!empty($existing_users)) {
      throw new \Exception('Email address is already registered');
    }

    // Check if username already exists
    $existing_users = $this->repository->getUserByUsername($user_info['email']);
    if (!empty($existing_users)) {
      throw new \Exception('Username is already taken');
    }

    // Generate random password
    $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

    // Create new user account
    $user = $this->repository->createUser([
      'name' => $user_info['username'],
      'mail' => $user_info['email'],
      'field_fullname' => $user_info['fullname'],
      'field_phone_number' => $user_info['phone'],
      'field_identification_code' => $user_info['identification_code'],
      'field_workplace' => $user_info['workplace'],
      'status' => 1,
      'pass' => $password,
    ]);

    // Check if user already registered for this class
    $existingRegistration = $this->repository->checkExistingRegistration($user->id(), $class->id());
    if (!empty($existingRegistration)) {
      throw new \Exception('Bạn đã đăng ký lớp học này rồi');
    }

    // Get current time and payment deadline
    $current_time = new \DateTime();
    $payment_deadline = new \DateTime('+7 days');

    // Create registration node
    $registration = $this->repository->createRegistration([
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

    // Add user to class's user list
    $this->repository->updateClassUserList($class, $user->id());

    // Send confirmation email
    $this->emailService->sendRegistrationConfirmation(
      $user_info['email'],
      $class,
      $user_info['fullname'],
      $course->get('field_course_tuition_fee')->value,
      $registration->id(),
      $user_info['username'],
      $password
    );

    return ['message' => 'Registration successful'];
  }

  protected function validateUserInfo($userInfo) {
    if (empty($userInfo['email']) || empty($userInfo['fullname']) || empty($userInfo['phone'])) {
      throw new \Exception('Email, full name and phone are required');
    }

    if (!filter_var($userInfo['email'], FILTER_VALIDATE_EMAIL)) {
      throw new \Exception('Invalid email format');
    }
  }
}