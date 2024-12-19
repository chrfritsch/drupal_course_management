<?php

namespace Drupal\docx_to_html\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the DOCX to HTML Converter.
 */
class DocxToHtmlController extends ControllerBase {

  /**
   * Returns the DOCX to HTML converter page.
   *
   * @return array
   *   A render array for the DOCX to HTML converter page.
   */
  public function content() {
    return [
      '#theme' => 'docx_to_html_page',
      '#attached' => [
        'library' => [
          'docx_to_html/docx_to_html',
        ],
      ],
    ];
  }

}
