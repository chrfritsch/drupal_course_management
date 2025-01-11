<?php

namespace Drupal\course_register\Interface;

/**
 *
 */
interface ClassRegistrationRepositoryInterface {

  /**
   *
   */
  public function checkExistingRegistration($userId, $classId);

  /**
   *
   */
  public function createRegistration(array $data);

  /**
   *
   */
  public function getClass($classId);

  /**
   *
   */
  public function getCourse($courseId);

  /**
   *
   */
  public function createUser(array $userData);

  /**
   *
   */
  public function updateClassUserList($class, $userId);

}
