<?php

namespace Drupal\course_register\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 *
 */
class CourseMaterialController extends ControllerBase {

  /**
   *
   */
  public function getMaterials($course_id) {
    // Lấy user hiện tại.
    $current_user = \Drupal::currentUser();

    // 1. Trước tiên lấy các class thuộc course này
    $class_query = \Drupal::entityQuery('node')
      ->condition('type', 'class')
      ->condition('field_class_course_reference', $course_id);
    $class_ids = $class_query->execute();

    if (empty($class_ids)) {
      return new JsonResponse([
        'message' => 'Không tìm thấy lớp học nào cho khóa học này',
      ], 404);
    }

    // 2. Sau đó kiểm tra registration của user cho các class đó
    $registration_query = \Drupal::entityQuery('node')
      ->condition('type', 'class_registration')
      ->condition('field_registration_user', $current_user->id())
      ->condition('field_registration_class', $class_ids, 'IN')
      ->condition('field_registration_status', 'confirmed');

    $registrations = $registration_query->execute();

    // Nếu chưa đăng ký, trả về thông báo lỗi.
    if (empty($registrations)) {
      return new JsonResponse([
        'message' => 'Bạn cần đăng ký khóa học này để xem tài liệu',
      ], 403);
    }

    // Nếu đã đăng ký, lấy danh sách tài liệu.
    $material_query = \Drupal::entityQuery('node')
      ->condition('type', 'course_material')
      ->condition('field_material_course', $course_id);

    $material_ids = $material_query->execute();
    $materials = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($material_ids);

    $result = [];
    foreach ($materials as $material) {
      // Lấy file entity từ field.
      $file_field = $material->get('field_material_file');
      $file = !$file_field->isEmpty() ? $file_field->entity : NULL;

      $result[] = [
        'id' => $material->id(),
        'title' => $material->getTitle(),
        'type' => $material->get('field_material_type')->value,
        'description' => $material->get('field_material_description')->value,
        'url' => $file ? $file->createFileUrl() : NULL,
      ];
    }

    return new JsonResponse($result);
  }

}
