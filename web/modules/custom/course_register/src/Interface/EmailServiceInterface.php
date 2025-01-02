<?php

namespace Drupal\course_register\Interface;

interface EmailServiceInterface {
  public function sendRegistrationConfirmation($to, $class, $userName, $price, $registrationId, $username = NULL, $password = NULL);
}