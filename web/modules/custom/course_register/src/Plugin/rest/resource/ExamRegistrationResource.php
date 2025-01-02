<?php

namespace Drupal\course_register\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\course_register\Interface\ExamRegistrationServiceInterface;

/**
 * @RestResource(
 *   id = "exam_registration",
 *   label = @Translation("Exam Registration"),
 *   uri_paths = {
 *     "create" = "/api/v1/exam-registration",
 *     "canonical" = "/api/v1/exam-registration/{id}"
 *   },
 *   authentication_providers = {
 *     "basic_auth"
 *   }
 * )
 */
class ExamRegistrationResource extends ResourceBase {

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
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The password generator.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passwordGenerator;

  /**
   * The exam registration service.
   *
   * @var \Drupal\course_register\Interface\ExamRegistrationServiceInterface
   */
  protected $examRegistrationService;

  /**
   * Constructs a new ExamRegistrationResource object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    PasswordGeneratorInterface $password_generator,
    ExamRegistrationServiceInterface $exam_registration_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->passwordGenerator = $password_generator;
    $this->examRegistrationService = $exam_registration_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('course_register'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('password_generator'),
      $container->get('course_register.exam_registration')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   The request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function post($data) {
    try {
      // Debug
      $request = \Drupal::request();
      \Drupal::logger('exam_registration')->notice('Auth header: @auth', [
        '@auth' => $request->headers->get('Authorization')
      ]);
      \Drupal::logger('exam_registration')->notice('User: @user', [
        '@user' => $this->currentUser->id()
      ]);

      // Validate required data
      if (empty($data['exam_id']) || empty($data['user_info'])) {
        throw new HttpException(400, 'Thiếu thông tin bắt buộc');
      }

      #region Kiểm tra xem user_info có đúng format không
      $this->examRegistrationService->validateUserInfo($data['user_info']);
      #endregion

      // Load exam node
      $exam = $this->entityTypeManager->getStorage('node')
        ->load($data['exam_id']);

      if (!$exam || $exam->bundle() !== 'exam_schedule') {
        throw new HttpException(404, 'Không tìm thấy kỳ thi');
      }

      // Validate exam status and capacity
      $this->examRegistrationService->validateExam($exam);

      // Handle registration based on user status
      if ($this->currentUser->isAuthenticated()) {
        $registration = $this->examRegistrationService->handleAuthenticatedRegistration($exam, $data['user_info']);
      }
      else {
        $registration = $this->examRegistrationService->handleAnonymousRegistration($exam, $data['user_info']);
      }

      // Send confirmation email
      $this->examRegistrationService->sendConfirmationEmail($registration);

      return new ResourceResponse([
        'message' => 'Đăng ký thành công',
        'registration_id' => $registration->id(),
        'payment_url' => '/payment/exam/' . $registration->id(),
      ], 201);
    }
    catch (\Exception $e) {
      throw new HttpException(500, $e->getMessage());
    }
  }
}
