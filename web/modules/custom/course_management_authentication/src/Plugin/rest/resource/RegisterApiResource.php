<?php

declare(strict_types=1);

namespace Drupal\course_management_authentication\Plugin\rest\resource;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Represents Register API records as resources.
 *
 * @RestResource (
 *   id = "course_management_authentication_register_api",
 *   label = @Translation("Register API"),
 *   uri_paths = {
 *     "create" = "/api/course-management-authentication-register-api"
 *   }
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
final class RegisterApiResource extends ResourceBase {

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $storage;

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
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->storage = $keyValueFactory->get('course_management_authentication_register_api');
  }

  /**
   *
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('keyvalue')
    );
  }

  /**
   *
   */
  public function post(array $data) {
    try {
      // Validate required fields.
      if (empty($data['mail']) || empty($data['pass'])) {
        throw new BadRequestHttpException('Email, mail, and password are required');
      }

      // Check if the user already exists.
      if (user_load_by_mail($data['mail'])) {
        throw new BadRequestHttpException('User already exists');
      }

      // Create a new user.
      $user = User::create();

      // Set required fields.
      $user->setUsername($data['name']);
      $user->setEmail($data['mail']);
      $user->setPassword($data['pass']);

      // Set additional fields if provided.
      if (!empty($data['field_fullname'])) {
        $user->set('field_fullname', $data['field_fullname']);
      }
      if (!empty($data['field_phone_number'])) {
        $user->set('field_phone_number', $data['field_phone_number']);
      }
      if (!empty($data['field_identification_code'])) {
        $user->set('field_identification_code', $data['field_identification_code']);
      }
      if (!empty($data['field_user_career'])) {
        $user->set('field_user_career', [
          'target_id' => $data['field_user_career'],
        ]);
      }
      if (!empty($data['field_workplace'])) {
        $user->set('field_workplace', $data['field_workplace']);
      }

      // Set role if specified.
      $user->addRole('student');

      // Activate the user.
      $user->activate();

      // Save the user.
      $user->save();

      // Return success response.
      return new ResourceResponse([
        'message' => 'User created successfully',
        'uid' => $user->id(),
      ], 200);
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
  }

}
