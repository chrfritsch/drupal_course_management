<?php

namespace Drupal\course_register\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Action\ActionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class ClassBulkOperationsForm extends FormBase {

  /**
   * The action manager service.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ClassBulkOperationsForm.
   */
  public function __construct(ActionManager $action_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->actionManager = $action_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.action'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'class_bulk_operations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Add Class button
    $form['add_class'] = [
      '#type' => 'link',
      '#title' => $this->t('Add class'),
      '#url' => \Drupal\Core\Url::fromRoute('node.add', ['node_type' => 'class']),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--action'],
      ],
      '#prefix' => '<div class="action-links">',
      '#suffix' => '</div>',
    ];

    // Table
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => [
        'title' => $this->t('Title'),
        'author' => $this->t('Author'),
        'status' => $this->t('Status'),
        'updated' => $this->t('Updated'),
        'operations' => $this->t('Operations'),
      ],
      '#empty' => $this->t('No content available.'),
      '#options' => [],
    ];

    // Get nodes
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'class')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Build rows
    foreach ($nodes as $node) {
      $form['table']['#options'][$node->id()] = [
        'title' => $node->toLink()->toString(),
        'author' => $node->getOwner()->getDisplayName(),
        'status' => $node->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
        'updated' => \Drupal::service('date.formatter')->format($node->getChangedTime(), 'short'),
        'operations' => [
          'data' => [
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
        ],
      ];
    }

    // Action select
    $form['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#options' => [
        'entity:delete_action:node' => $this->t('Delete selected content'),
        'entity:publish_action:node' => $this->t('Publish selected content'),
        'entity:unpublish_action:node' => $this->t('Unpublish selected content'),
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue('table'));
    $action = $form_state->getValue('action');

    if (!empty($selected) && !empty($action)) {
      $action_instance = $this->actionManager->createInstance($action);
      $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($selected);
      
      foreach ($entities as $entity) {
        $action_instance->execute($entity);
      }

      $this->messenger()->addMessage($this->t('The update has been performed.'));
    }
  }
}