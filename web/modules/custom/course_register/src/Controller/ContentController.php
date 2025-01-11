<?php

namespace Drupal\course_register\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for custom content listing pages.
 */
class ContentController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a ContentController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Builds the response for the class content listing page.
   */
  // public function classContent() {
  //   // Khởi tạo form để xử lý bulk operations
  //   $form['admin_table'] = [
  //     '#type' => 'form',
  //     '#attributes' => ['id' => 'class-admin-table'],
  //   ];

  //   // Thêm nút Add Class
  //   $form['admin_table']['add_class'] = [
  //     '#type' => 'link',
  //     '#title' => $this->t('Add class'),
  //     '#url' => \Drupal\Core\Url::fromRoute('node.add', ['node_type' => 'class']),
  //     '#attributes' => [
  //       'class' => ['button', 'button--primary', 'button--action'],
  //     ],
  //     '#prefix' => '<div class="action-links">',
  //     '#suffix' => '</div>',
  //   ];

  //   // Định nghĩa bảng với checkbox
  //   $form['admin_table']['table'] = [
  //     '#type' => 'table',
  //     '#header' => [
  //       'checkbox' => ['data' => '', 'class' => ['select-all']],
  //       'title' => $this->t('Title'),
  //       'author' => $this->t('Author'),
  //       'status' => $this->t('Status'),
  //       'updated' => $this->t('Updated'),
  //       'operations' => $this->t('Operations'),
  //     ],
  //     '#empty' => $this->t('No content available.'),
  //   ];

  //   // Thêm bulk operations
  //   $form['admin_table']['actions'] = [
  //     '#type' => 'container',
  //     '#attributes' => ['class' => ['form-actions js-form-wrapper']],
  //     'action' => [
  //       '#type' => 'select',
  //       '#title' => $this->t('Action'),
  //       '#options' => [
  //         'node_delete_action' => $this->t('Delete selected content'),
  //         'node_publish_action' => $this->t('Publish selected content'),
  //         'node_unpublish_action' => $this->t('Unpublish selected content'),
  //       ],
  //     ],
  //     'submit' => [
  //       '#type' => 'submit',
  //       '#value' => $this->t('Apply to selected items'),
  //       '#button_type' => 'primary',
  //     ],
  //   ];

  //   $query = $this->entityTypeManager->getStorage('node')->getQuery()
  //     ->condition('type', 'class')
  //     ->sort('created', 'DESC')
  //     ->accessCheck(TRUE);

  //   $nids = $query->execute();
  //   $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

  //   foreach ($nodes as $node) {
  //     $form['admin_table']['table'][$node->id()] = [
  //       'checkbox' => [
  //         '#type' => 'checkbox',
  //         '#title' => $this->t('Update this item'),
  //         '#title_display' => 'invisible',
  //         '#return_value' => $node->id(),
  //       ],
  //       'title' => [
  //         '#markup' => $node->toLink()->toString(),
  //       ],
  //       'author' => [
  //         '#markup' => $node->getOwner()->getDisplayName(),
  //       ],
  //       'status' => [
  //         '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
  //       ],
  //       'updated' => [
  //         '#markup' => \Drupal::service('date.formatter')
  //           ->format($node->getChangedTime(), 'short'),
  //       ],
  //       'operations' => [
  //         '#type' => 'operations',
  //         '#links' => [
  //           'edit' => [
  //             'title' => $this->t('Edit'),
  //             'url' => $node->toUrl('edit-form'),
  //           ],
  //           'delete' => [
  //             'title' => $this->t('Delete'),
  //             'url' => $node->toUrl('delete-form'),
  //           ],
  //         ],
  //       ],
  //     ];
  //   }

  //   $form['#attached']['library'][] = 'core/drupal.tableselect';
  //   return $form;
  // }

  /**
   * Builds the response for the course content listing page.
   */
  public function courseContent() {
    // Thêm nút Add Course
    $build['add_course'] = [
      '#type' => 'link',
      '#title' => $this->t('Add course'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'course']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Author'),
        $this->t('Status'),
        $this->t('Updated'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'course')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $build['table'][] = [
        'title' => [
          '#markup' => $node->toLink()->toString(),
        ],
        'author' => [
          '#markup' => $node->getOwner()->getDisplayName(),
        ],
        'status' => [
          '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        ],
        'updated' => [
          '#markup' => \Drupal::service('date.formatter')
            ->format($node->getChangedTime(), 'short'),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $node->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $node->toUrl('delete-form'),
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds the response for the class registered content listing page.
   */
  public function classRegisteredContent() {
    // Thêm nút Add Class Registration
    $build['add_class_registered'] = [
      '#type' => 'link',
      '#title' => $this->t('Add class registration'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'class_registered']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Author'),
        $this->t('Status'),
        $this->t('Updated'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'class_registered')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $build['table'][] = [
        'title' => [
          '#markup' => $node->toLink()->toString(),
        ],
        'author' => [
          '#markup' => $node->getOwner()->getDisplayName(),
        ],
        'status' => [
          '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        ],
        'updated' => [
          '#markup' => \Drupal::service('date.formatter')
            ->format($node->getChangedTime(), 'short'),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $node->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $node->toUrl('delete-form'),
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds the response for the transaction content listing page.
   */
  public function transactionContent() {
    // Thêm nút Add Transaction
    $build['add_transaction'] = [
      '#type' => 'link',
      '#title' => $this->t('Add transaction'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'transaction_history']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Author'),
        $this->t('Status'),
        $this->t('Updated'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'transaction_history')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $build['table'][] = [
        'title' => [
          '#markup' => $node->toLink()->toString(),
        ],
        'author' => [
          '#markup' => $node->getOwner()->getDisplayName(),
        ],
        'status' => [
          '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        ],
        'updated' => [
          '#markup' => \Drupal::service('date.formatter')
            ->format($node->getChangedTime(), 'short'),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $node->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $node->toUrl('delete-form'),
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds the response for the exam schedule content listing page.
   */
  public function examScheduleContent() {
    // Thêm nút Add Transaction
    $build['add_exam_schedule'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Exam Schedule'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'exam_schedule']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Author'),
        $this->t('Status'),
        $this->t('Updated'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'exam_schedule')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $build['table'][] = [
        'title' => [
          '#markup' => $node->toLink()->toString(),
        ],
        'author' => [
          '#markup' => $node->getOwner()->getDisplayName(),
        ],
        'status' => [
          '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        ],
        'updated' => [
          '#markup' => \Drupal::service('date.formatter')
            ->format($node->getChangedTime(), 'short'),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $node->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $node->toUrl('delete-form'),
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds the response for the student scores content listing page.
   */
  public function studentScoresContent() {
    // Thêm nút Add Transaction
    $build['add_student_scores'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Student Scores'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'student_scores']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Author'),
        $this->t('Status'),
        $this->t('Updated'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'student_scores')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $build['table'][] = [
        'title' => [
          '#markup' => $node->toLink()->toString(),
        ],
        'author' => [
          '#markup' => $node->getOwner()->getDisplayName(),
        ],
        'status' => [
          '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        ],
        'updated' => [
          '#markup' => \Drupal::service('date.formatter')
            ->format($node->getChangedTime(), 'short'),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $node->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $node->toUrl('delete-form'),
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds the response for the certificate content listing page.
   */
  public function certificateContent() {
    // Thêm nút Add Transaction
    $build['add_certificate'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Certificate'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'certificate']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Author'),
        $this->t('Status'),
        $this->t('Updated'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'certificate')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $build['table'][] = [
        'title' => [
          '#markup' => $node->toLink()->toString(),
        ],
        'author' => [
          '#markup' => $node->getOwner()->getDisplayName(),
        ],
        'status' => [
          '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        ],
        'updated' => [
          '#markup' => \Drupal::service('date.formatter')
            ->format($node->getChangedTime(), 'short'),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $node->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $node->toUrl('delete-form'),
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  public function newsContent() {
    // Thêm nút Add Transaction
    $build['add_news'] = [
      '#type' => 'link',
      '#title' => $this->t('Add News'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'news']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Author'),
        $this->t('Status'),
        $this->t('Updated'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'news')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $build['table'][] = [
        'title' => [
          '#markup' => $node->toLink()->toString(),
        ],
        'author' => [
          '#markup' => $node->getOwner()->getDisplayName(),
        ],
        'status' => [
          '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        ],
        'updated' => [
          '#markup' => \Drupal::service('date.formatter')
            ->format($node->getChangedTime(), 'short'),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $node->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $node->toUrl('delete-form'),
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  // Tương tự cho các method khác: courseContent(), classRegisteredContent(), transactionContent()
  // Copy cấu trúc tương tự nhưng thay đổi condition type tương ứng
}
