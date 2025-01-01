<?php

namespace Drupal\course_register\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;

/**
 * Service for handling receipts.
 */
class ReceiptService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ReceiptService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Create a new receipt.
   */
  public function createReceipt($data) {
    try {
      // Generate receipt number
      $receipt_number = $this->generateReceiptNumber();

      // Prepare class references
      $class_references = [];
      if (isset($data['class_id'])) {
        // Support old format
        $class_references[] = ['target_id' => $data['class_id']];
      }
      elseif (isset($data['class_ids']) && is_array($data['class_ids'])) {
        // Support multiple classes
        foreach ($data['class_ids'] as $class_id) {
          $class_references[] = ['target_id' => $class_id];
        }
      }

      // Create receipt node
      $receipt = Node::create([
        'type' => 'receipt',
        'title' => 'Receipt #' . $receipt_number,
        'field_receipt_number' => $receipt_number,
        'field_receipt_date' => date('Y-m-d\TH:i:s'),
        'field_receipt_amount' => $data['amount'],
        'field_receipt_payment_method' => $data['payment_method'],
        'field_receipt_transaction_id' => $data['transaction_id'],
        'field_receipt_student' => ['target_id' => $data['user_id']],
        'field_receipt_class' => $class_references,
        'field_receipt_status' => 'completed',
        'status' => 1,
      ]);

      $receipt->save();
      return $receipt;
    }
    catch (\Exception $e) {
      \Drupal::logger('course_register')
        ->error('Error creating receipt: @error', [
          '@error' => $e->getMessage(),
        ]);
      throw $e;
    }
  }

  /**
   * Generate unique receipt number.
   */
  private function generateReceiptNumber() {
    $prefix = 'R';
    $timestamp = date('YmdHis');
    $random = substr(str_shuffle('0123456789'), 0, 4);
    return $prefix . $timestamp . $random;
  }

  /**
   * Get receipt by transaction ID.
   */
  public function getReceiptByTransactionId($transaction_id) {
    $receipts = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'receipt',
      'field_receipt_transaction_id' => $transaction_id,
    ]);
    return reset($receipts);
  }

}
