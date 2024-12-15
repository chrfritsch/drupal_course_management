<?php

namespace Drupal\user;

/**
 * An interface for validating user course_management_authentication credentials.
 */
interface UserAuthInterface {

  /**
   * Validates user course_management_authentication credentials.
   *
   * @param string $username
   *   The user name to authenticate.
   * @param string $password
   *   A plain-text password, such as trimmed text from form values.
   *
   * @return int|bool
   *   The user's uid on success, or FALSE on failure to authenticate.
   */
  public function authenticate($username, #[\SensitiveParameter] $password);

}
