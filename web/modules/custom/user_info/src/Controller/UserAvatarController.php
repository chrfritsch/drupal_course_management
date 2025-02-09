<?php

namespace Drupal\user_info\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

// Thêm dòng này.
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 *
 */
class UserAvatarController extends ControllerBase {

  /**
   *
   */
  public function upload($uid, Request $request) {
    try {
      // Kiểm tra quyền truy cập.
      if (!$this->currentUser()->hasPermission('edit any user profile') &&
        $this->currentUser()->id() != $uid) {
        throw new HttpException(403, 'Không có quyền chỉnh sửa thông tin người dùng.');
      }

      // Load user entity.
      $user = $this->entityTypeManager()->getStorage('user')->load($uid);
      if (!$user) {
        throw new HttpException(404, 'Không tìm thấy người dùng.');
      }

      $files = $request->files;
      if (!$files->has('avatar')) {
        throw new HttpException(400, 'Không tìm thấy file avatar.');
      }

      $file = $files->get('avatar');

      // Kiểm tra file type sử dụng FileInfo.
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      $mime_type = $finfo->file($file->getRealPath());

      // Debug.
      \Drupal::logger('user_info')->notice('File mime type: @mime', [
        '@mime' => $mime_type,
      ]);

      $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
      if (!in_array($mime_type, $allowed_types)) {
        throw new HttpException(400, sprintf(
          'File không đúng định dạng (%s). Chỉ chấp nhận JPG, PNG, GIF.',
          $mime_type
        ));
      }

      // Lưu file.
      $file_system = \Drupal::service('file_system');
      $directory = 'public://avatars';
      $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $file_entity = File::create([
        'uri' => $file_system->saveData(
          file_get_contents($file->getRealPath()),
          $directory . '/' . $file->getClientOriginalName(),
          FileSystemInterface::EXISTS_RENAME
        ),
        'filename' => $file->getClientOriginalName(),
        'filemime' => $mime_type,
      // Sửa dòng này.
        'status' => FileInterface::STATUS_PERMANENT,
      ]);
      $file_entity->save();

      // Cập nhật avatar cho user.
      $user->set('field_avatar', $file_entity->id());
      $user->save();

      return new JsonResponse([
        'message' => 'Cập nhật avatar thành công',
        'avatar_url' => $file_entity->createFileUrl(),
      ]);
    }
    catch (\Exception $e) {
      throw new HttpException(500, 'Có lỗi xảy ra: ' . $e->getMessage());
    }
  }

}
