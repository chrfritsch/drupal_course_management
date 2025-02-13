<?php

namespace Drupal\course_register\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\entity_print\Plugin\PrintEngineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for handling receipt downloads.
 */
class ReceiptController extends ControllerBase {

  /**
   * The print builder.
   *
   * @var \Drupal\entity_print\PrintBuilderInterface
   */
  protected $printBuilder;

  /**
   * The print engine.
   *
   * @var \Drupal\entity_print\Plugin\PrintEngineInterface
   */
  protected $printEngine;

  /**
   * Constructs a new ReceiptController object.
   */
  public function __construct(PrintBuilderInterface $printBuilder, PrintEngineInterface $printEngine) {
    $this->printBuilder = $printBuilder;
    $this->printEngine = $printEngine;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_print.print_builder'),
      $container->get('plugin.manager.entity_print.print_engine')->createInstance('tcpdfv1')
    );
  }

  /**
   * Downloads a PDF version of the receipt.
   */
  public function downloadPdf($receipt_id) {
    // Load receipt node.
    $receipt = $this->entityTypeManager()
      ->getStorage('node')
      ->load($receipt_id);

    if (!$receipt || $receipt->bundle() !== 'receipt') {
      throw new NotFoundHttpException('Không tìm thấy biên lai.');
    }

    // Kiểm tra quyền truy cập.
    if (!$receipt->access('view')) {
      throw new NotFoundHttpException('Bạn không có quyền xem biên lai này.');
    }

    // Generate PDF.
    $entities = [$receipt];
    return $this->printBuilder->deliverPrintable($entities, $this->printEngine, TRUE);
  }

}
