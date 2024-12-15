<?php

namespace Drupal\Core\Authentication;

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for course_management_authentication providers.
 */
interface AuthenticationProviderInterface {

  /**
   * Checks whether suitable course_management_authentication credentials are on the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if course_management_authentication credentials suitable for this provider are on the
   *   request, FALSE otherwise.
   */
  public function applies(Request $request);

  /**
   * Authenticates the user.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request object.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   AccountInterface - in case of a successful course_management_authentication.
   *   NULL - in case where course_management_authentication failed.
   */
  public function authenticate(Request $request);

}
