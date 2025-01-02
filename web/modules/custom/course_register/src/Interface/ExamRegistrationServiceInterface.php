<?php

namespace Drupal\course_register\Interface;

interface ExamRegistrationServiceInterface {
  /**
   * Validate user information.
   *
   * @param array $user_info
   *   The user information array.
   */
  public function validateUserInfo(array $user_info);

  /**
   * Validate exam status and capacity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $exam
   *   The exam node.
   */
  public function validateExam($exam);

  /**
   * Handle registration for authenticated user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $exam
   *   The exam node.
   * @param array $user_info
   *   The user information array.
   *
   * @return \Drupal\node\Entity\Node
   *   The registration node.
   */
  public function handleAuthenticatedRegistration($exam, array $user_info);

  /**
   * Handle registration for anonymous user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $exam
   *   The exam node.
   * @param array $user_info
   *   The user information array.
   *
   * @return \Drupal\node\Entity\Node
   *   The registration node.
   */
  public function handleAnonymousRegistration($exam, array $user_info);

  /**
   * Send confirmation email.
   *
   * @param \Drupal\node\Entity\Node $registration
   *   The registration node.
   */
  public function sendConfirmationEmail($registration);

  /**
   * Get registered candidates count.
   *
   * @param int $exam_id
   *   The exam node ID.
   *
   * @return int
   *   The count of registered candidates.
   */
  public function getRegisteredCount($exam_id);

  /**
   * Convert fullname to username.
   *
   * @param string $fullname
   *   The full name.
   *
   * @return string
   *   The username.
   */
  public function convertFullnameToUsername($fullname);

  /**
   * Remove accents from string.
   *
   * @param string $string
   *   The input string.
   *
   * @return string
   *   The string without accents.
   */
  public function removeAccents($string);
}
