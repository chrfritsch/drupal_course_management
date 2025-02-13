<?php

namespace Drupal\course_register\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\course_register\Service\ClassRegistrationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @RestResource(
 *   id = "class_registration",
 *   label = @Translation("Class registration"),
 *   uri_paths = {
 *     "create" = "/api/v1/class-registration"
 *   }
 * )
 */
class ClassRegistrationResource extends ResourceBase {
  protected $registrationService;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    ClassRegistrationService $registrationService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->registrationService = $registrationService;
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
      $container->get('logger.factory')->get('course_register'),
      $container->get('course_register.registration_service')
    );
  }

  /**
   * Đầu tiên khi gửi request POST đến endpoint này, hàm post() sẽ được gọi.
   * Tham số $data chính là dữ liệu mà client gửi lên.
   * $data sẽ chứa thông tin dạng như sau:
   * [
   *   'class_id' => 'ID của lớp học',
   *   'user_info' => [
   *      'username' => 'thumen2003',
   *      'email' => 'Email người dùng',
   *      'fullname' => 'Nguyen Thi Thu Men',
   *      'phone' => '+84 899 322 460',
   *      'identification_code' => '086202004688', // Cái này lã số căn cước công dân
   *      'workplace' => '280 An Duong Vuong'
   *   ]
   * ]
   *
   * @param array $data
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function post($data) {
    try {
      return new ResourceResponse(
        $this->registrationService->registerForClass($data)
      );
    }
    catch (\Exception $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 400);
    }
  }

}
