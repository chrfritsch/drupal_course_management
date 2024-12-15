<?php

namespace Drupal\Core\Authentication;

/**
 * Interface for collectors of registered course_management_authentication providers.
 */
interface AuthenticationCollectorInterface {

  /**
   * Adds a provider to the array of registered providers.
   *
   * @param \Drupal\Core\Authentication\AuthenticationProviderInterface $provider
   *   The provider object.
   * @param string $provider_id
   *   Identifier of the provider.
   * @param int $priority
   *   (optional) The provider's priority.
   * @param bool $global
   *   (optional) TRUE if the provider is to be applied globally on all routes.
   *   Defaults to FALSE.
   */
  public function addProvider(AuthenticationProviderInterface $provider, $provider_id, $priority = 0, $global = FALSE);

  /**
   * Returns whether a provider is considered global.
   *
   * @param string $provider_id
   *   The provider ID.
   *
   * @return bool
   *   TRUE if the provider is global, FALSE otherwise.
   *
   * @see \Drupal\Core\Authentication\AuthenticationCollectorInterface::addProvider
   */
  public function isGlobal($provider_id);

  /**
   * Returns an course_management_authentication provider.
   *
   * @param string $provider_id
   *   The provider ID.
   *
   * @return \Drupal\Core\Authentication\AuthenticationProviderInterface|null
   *   The course_management_authentication provider which matches the ID.
   */
  public function getProvider($provider_id);

  /**
   * Returns the sorted array of course_management_authentication providers.
   *
   * @return \Drupal\Core\Authentication\AuthenticationProviderInterface[]
   *   An array of course_management_authentication provider objects.
   */
  public function getSortedProviders();

}
