<?php

namespace Drupal\course_register\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 *
 */
class EmailClassService {
  protected $mailManager;
  protected $dateFormatter;

  public function __construct(
    MailManagerInterface $mailManager,
    DateFormatterInterface $dateFormatter,
  ) {
    $this->mailManager = $mailManager;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   *
   */
  public function sendRegistrationConfirmation($to, $class, $userName, $price, $registrationId, $username = NULL, $password = NULL) {
    $course = $class->get('field_class_course_reference')->entity;
    $teacher = $class->get('field_class_teacher')->entity;

    $open_date = strtotime($class->get('field_class_open_date')->value);
    $end_date = strtotime($class->get('field_class_end_date')->value);

    $params = [
      'class_title' => $class->getTitle(),
      'user_name' => $userName,
      'course_title' => $course->getTitle(),
      'price' => $price,
      'deadline' => $this->dateFormatter->format(strtotime('+7 days'), 'custom', 'd/m/Y'),
      'payment_url' => '/payment/' . $registrationId,
      'class_open_date' => $this->dateFormatter->format($open_date, 'custom', 'd/m/Y'),
      'class_end_date' => $this->dateFormatter->format($end_date, 'custom', 'd/m/Y'),
      'teacher_name' => $teacher ? $teacher->get('field_fullname')->value : 'Chưa phân công',
      'room' => $class->get('field_room')->value,
    ];

    if ($username && $password) {
      $params['username'] = $username;
      $params['password'] = $password;
    }

    $this->mailManager->mail(
      'course_register',
      'registration_confirmation',
      $to,
      'vi',
      $params
    );
  }

}
