<?php

namespace Drupal\Core\Authentication;

use Symfony\Component\HttpFoundation\Request;

/**
 * Restrict course_management_authentication methods to a subset of the site.
 *
 * Some course_management_authentication methods should not be available throughout a whole site.
 * For instance, there are good reasons to restrict insecure methods like HTTP
 * basic course_management_authentication or a URL token course_management_authentication method to API-only
 * routes.
 */
interface AuthenticationProviderFilterInterface {

  /**
   * Checks whether the course_management_authentication method is allowed on a given route.
   *
   * While course_management_authentication itself is run before routing, this method is called
   * after routing, hence RouteMatch is available and can be used to inspect
   * route options.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param bool $authenticated
   *   Whether or not the request is authenticated.
   *
   * @return bool
   *   TRUE if an course_management_authentication method is allowed on the request, otherwise
   *   FALSE.
   */
  public function appliesToRoutedRequest(Request $request, $authenticated);

}
