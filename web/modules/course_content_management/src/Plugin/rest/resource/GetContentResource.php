<?php

declare(strict_types=1);

namespace Drupal\course_content_management\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;

/**
 * Represents Get content records as resources.
 *
 * @RestResource (
 *   id = "course_content_management_get_content",
 *   label = @Translation("Get content"),
 *   uri_paths = {
 *     "canonical" = "/api/course-content-management-get-content/{id}",
 *     "canonical" = "/api/course-content-management-get-content"
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
final class GetContentResource extends ResourceBase {

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
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->storage = $keyValueFactory->get('course_content_management_get_content');
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get($id = NULL): ResourceResponse {
    // If no ID is provided, return a list of all articles.
    if ($id === NULL) {
      $articles = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['type' => 'article']);
      $response_data = [];
      foreach ($articles as $article) {
        $response_data[] = $this->serializeArticle($article);
      }
      return new ResourceResponse($response_data);
    }

    // Load the specific article.
    $article = $this->entityTypeManager
      ->getStorage('node')
      ->load($id);

    // Check if article exists and is of type 'article'.
    if (!$article || $article->bundle() !== 'article') {
      throw new NotFoundHttpException('Article not found');
    }

    // Serialize the article.
    $response_data = $this->serializeArticle($article);
    return new ResourceResponse($response_data);
  }

  protected function serializeArticle($article) {
    return [
      'id' => $article->id(),
      'title' => $article->getTitle(),
      'body' => $article->get('body')->value,
      'image' => $article->get('field_image')->value,
      'created' => $article->getCreatedTime(),
      'author' => $article->getOwner()->getDisplayName(),
    ];
  }

}
