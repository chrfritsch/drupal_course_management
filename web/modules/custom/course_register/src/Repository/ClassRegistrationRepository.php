<?php

namespace Drupal\course_register\Repository;

use Drupal\course_register\Interface\ClassRegistrationRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

class ClassRegistrationRepository implements ClassRegistrationRepositoryInterface {
  protected $entityTypeManager;
  protected $database;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
  }

  public function checkExistingRegistration($userId, $classId) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'class_registration')
      ->condition('field_registration_class', $classId)
      ->condition('field_registration_user', $userId)
      ->accessCheck(FALSE);

    return $query->execute();
  }

  public function getClass($classId) {
    return $this->entityTypeManager->getStorage('node')->load($classId);
  }

  public function getCourse($courseId) {
    return $this->entityTypeManager->getStorage('node')->load($courseId);
  }

  public function getUser($userId) {
    return $this->entityTypeManager->getStorage('user')->load($userId);
  }

  public function getUserByEmail($email) {
    return $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['mail' => $email]);
  }

  public function getUserByUsername($username) {
    return $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => $username]);
  }

  public function createUser($userData) {
    $user = $this->entityTypeManager->getStorage('user')->create($userData);
    $user->addRole('student');
    $user->save();
    return $user;
  }

  public function createRegistration($data) {
    $registration = $this->entityTypeManager->getStorage('node')->create($data);
    $registration->save();
    return $registration;
  }

  public function updateClassUserList($class, $userId) {
    $current_user_list = $class->get('field_user_list')->getValue();
    $current_user_list[] = ['target_id' => $userId];
    $class->set('field_user_list', $current_user_list);
    $class->save();
  }
}