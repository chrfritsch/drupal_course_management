<?php

declare(strict_types=1);

namespace Drupal\user_info\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\Entity\File;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;

/**
 * Represents API records as resources.
 *
 * @RestResource(
 *   id = "user_info_api",
 *   label = @Translation("API"),
 *   uri_paths = {
 *     "canonical" = "/api/user-info-api/{uid}"
 *   },
 *   methods = {
 *     "GET",
 *     "PATCH"
 *   }
 * )
 *
 * @DCG
 * The plugin exposes key-value records as REST resources. In order to enable
 *   it
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
final class UserProfileResource extends ResourceBase {

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $storage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    AccountProxyInterface $currentUser,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->storage = $keyValueFactory->get('user_info_api');
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing user data.
   */
  public function get($uid) {
    try {
      // Kiểm tra quyền truy cập
//      if (!$this->currentUser->hasPermission('access user profiles')) {
//        throw new HttpException(403, 'Không có quyền truy cập thông tin người dùng.');
//      }

      // Load user entity
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!$user) {
        throw new HttpException(404, 'Không tìm thấy người dùng.');
      }

      // Chuẩn bị dữ liệu trả về
      $data = [
        'uid' => $user->id(),
        'username' => $user->getAccountName(),
        'email' => $user->getEmail(),
        'fullname' => $user->get('field_fullname')->value,
        'identification_code' => $user->get('field_identification_code')->value,
        'phone_number' => $user->get('field_phone_number')->value,
        'workplace' => $user->get('field_workplace')->value,
        'avatar' => $user->get('field_avatar')->entity ? $user->get('field_avatar')->entity->createFileUrl() : NULL,
      ];
  
      if ($user->get('field_user_career')->entity) {
        $data['career'] = [
          'tid' => $user->get('field_user_career')->entity->id(),
          'name' => $user->get('field_user_career')->entity->getName(),
        ];
      }
  
      $response = new ResourceResponse($data);
      // Disable caching
      $response->addCacheableDependency($user);
      $response->getCacheableMetadata()->setCacheMaxAge(0);
      
      return $response;
    }
    catch (\Exception $e) {
      throw new HttpException(500, 'Có lỗi xảy ra: ' . $e->getMessage());
    }
  }

  /**
   * Responds to PATCH requests.
   *
   * @param int $uid
   *   The user ID.
   * @param array $data
   *   The user data to update.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the updated user data.
   */
  public function patch($uid, array $data) {
    try {
      // Kiểm tra quyền truy cập
//      if (!$this->currentUser->hasPermission('edit any user profile') &&
//        $this->currentUser->id() != $uid) {
//        throw new HttpException(403, 'Không có quyền chỉnh sửa thông tin người dùng.');
//      }

      // Load user entity
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!$user) {
        throw new HttpException(404, 'Không tìm thấy người dùng.');
      }

      // Cập nhật thông tin cơ bản
      $fields_to_update = [
        'fullname' => 'field_fullname',
        'identification_code' => 'field_identification_code',
        'phone_number' => 'field_phone_number',
        'workplace' => 'field_workplace',
      ];

      foreach ($fields_to_update as $key => $field) {
        if (isset($data[$key])) {
          $user->set($field, $data[$key]);
        }
      }

      // Cập nhật career nếu có
      if (isset($data['career']) && !empty($data['career']['tid'])) {
        $user->set('field_user_career', $data['career']['tid']);
      }

      // Lưu thay đổi
      $user->save();

      // Chuẩn bị dữ liệu trả về
      $response_data = [
        'uid' => $user->id(),
        'username' => $user->getAccountName(),
        'email' => $user->getEmail(),
        'fullname' => $user->get('field_fullname')->value,
        'identification_code' => $user->get('field_identification_code')->value,
        'phone_number' => $user->get('field_phone_number')->value,
        'workplace' => $user->get('field_workplace')->value,
        'avatar' => $user->get('field_avatar')->entity ? $user->get('field_avatar')->entity->createFileUrl() : NULL,
      ];

      // Thêm thông tin career nếu có
      if ($user->get('field_user_career')->entity) {
        $response_data['career'] = [
          'tid' => $user->get('field_user_career')->entity->id(),
          'name' => $user->get('field_user_career')->entity->getName(),
        ];
      }

      return new ResourceResponse($response_data);
    }
    catch (\Exception $e) {
      throw new HttpException(500, 'Có lỗi xảy ra: ' . $e->getMessage());
    }
  }

  /**
   * Helper function to get files from request.
   */
  private function getRequestFiles() {
    return \Drupal::request()->files->all();
  }

  /**
   * Helper function to save uploaded file.
   */
  private function saveFile($uploaded_file, $directory) {
    $file_system = \Drupal::service('file_system');
    $directory = $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    if ($directory) {
      $file = File::create([
        'uri' => $file_system->saveData(
          file_get_contents($uploaded_file->getRealPath()),
          $directory . '/' . $uploaded_file->getClientOriginalName(),
          FileSystemInterface::EXISTS_RENAME
        ),
        'filename' => $uploaded_file->getClientOriginalName(),
        'filemime' => $uploaded_file->getMimeType(),
      ]);
      $file->setPermanent();
      $file->save();
      return $file;
    }
    return NULL;
  }

}
