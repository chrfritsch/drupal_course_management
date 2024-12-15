<?php

declare(strict_types=1);

namespace Drupal\course_management_authentication\Plugin\rest\resource;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\UserAuthInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Route;

/**
 * Represents Login API records as resources.
 *
 * @RestResource (
 *   id = "course_management_authentication_login_api",
 *   label = @Translation("Login API"),
 *   uri_paths = {
 *     "create" = "/api/course-management-authentication-login-api"
 *   },
 *   serialization_class = "Drupal\user\Entity\User",
 *   authentication = {
 *     "cookie"
 *   },
 *   csrf_protection = false
 * )
 *
 * @DCG
 * The plugin exposes key-value records as REST resources. In order to enable it
 * import the resource configuration into active configuration storage. An
 * example of such configuration can be located in the following file:
 * core/modules/rest/config/optional/rest.resource.entity.node.yml.
 * Alternatively, you can enable it through admin interface provider by REST UI
 * module.
 * @see https://www.drupal.org/project/restui
 *
 * @DCG
 * Notice that this plugin does not provide any validation for the data.
 * Consider creating custom normalizer to validate and normalize the incoming
 * data. It can be enabled in the plugin definition as follows.
 * @code
 *   serialization_class = "Drupal\foo\MyDataStructure",
 * @endcode
 *
 * @DCG
 * For entities, it is recommended to use REST resource plugin provided by
 * Drupal core.
 * @see \Drupal\rest\Plugin\rest\resource\EntityResource
 */
final class LoginApiResource extends ResourceBase {

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $storage;

  /**
   * The user auth servie.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    KeyValueFactoryInterface $keyValueFactory,
    UserAuthInterface $userAuth,
    CsrfTokenGenerator $csrfToken
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->storage = $keyValueFactory->get('course_management_authentication_login_api');
    $this->userAuth = $userAuth;
    $this->csrfToken = $csrfToken;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('keyvalue'),
      $container->get('user.auth'),
      $container->get('csrf_token')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   The data array containing user credentials.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing user data.
   */
  public function post(array $data) {
    try {
      // Validate required fields.
      if (empty($data['mail']) || empty($data['pass'])) {
        throw new BadRequestHttpException('Email and password are required');
      }

      // Load user by email.
      $users = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['mail' => $data['mail']]);
      $user = reset($users);

      if (!$user) {
        throw new UnauthorizedHttpException('basic', 'Invalid email or password');
      }

      // Verify password.
      if ($this->userAuth->authenticate($user->getAccountName(), $data['pass'])) {
        // Generate tokens
        $csrf_token = $this->csrfToken->get('rest');
        $logout_token = $this->csrfToken->get('user/logout');

        // Prepare response.
        $response_data = [
          'current_user' => [
            'uid' => $user->id(),
            'roles' => $user->getRoles(),
            'name' => $user->getAccountName(),
          ],
          'csrf_token' => $csrf_token,
          'logout_token' => $logout_token,
        ];

        $response = new ResourceResponse($response_data);

        // Ensure the response is not cached.
        $response->addCacheableDependency($user);
        $metadata = $response->getCacheableMetadata();
        $metadata->addCacheContexts(['user.roles:authenticated']);
        $metadata->addCacheTags(['user:' . $user->id()]);

        return $response;
      }

      throw new UnauthorizedHttpException('basic', 'Invalid email or password');
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
  }

}
