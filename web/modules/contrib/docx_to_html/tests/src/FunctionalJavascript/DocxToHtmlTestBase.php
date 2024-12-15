<?php

namespace Drupal\Tests\docx_to_html\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for DOCX to HTML FunctionalJavascript tests.
 */
abstract class DocxToHtmlTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['docx_to_html', 'block'];

  /**
   * The default theme used during testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to access the DOCX to HTML converter.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * Sets up the test environment.
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a user with permission to access the converter.
    $this->webUser = $this->drupalCreateUser(['access docx to html converter']);
    $this->drupalLogin($this->webUser);
  }

  /**
   * Asserts that the initial state of the converter page is correct.
   */
  protected function assertInitialPageState() {
    // Visit the DOCX to HTML converter page.
    $this->drupalGet('/docx-to-html');

    // Verify the page elements exist.
    $this->assertSession()->fieldExists('document');
    $this->assertSession()->elementExists('css', '#output');
    $this->assertSession()->elementExists('css', '#copy-button');

    // Verify that the output and copy button are initially hidden.
    $this->assertFalse($this->getSession()->getPage()->find('css', '#output')->isVisible(), "Output element is visible.");
    $this->assertFalse($this->getSession()->getPage()->find('css', '#copy-button')->isVisible(), "Copy button is visible.");
  }

  /**
   * Uploads a file and waits for the conversion to complete.
   *
   * @param string $filePath
   *   The path to the file to upload.
   */
  protected function uploadFileAndWaitForConversion($filePath) {
    $full_path = $this->getTestFilesPath() . $filePath;
    $this->getSession()->getPage()->attachFileToField('document', $full_path);
    // Wait for the conversion to complete and the output to be visible.
    $this->assertSession()->waitForElementVisible('css', '#output');
  }

  /**
   * Returns the path to the directory containing the test files.
   *
   * @return string
   *   The path to the test files directory.
   */
  protected function getTestFilesPath() {
    return \Drupal::service('extension.list.module')->getPath('docx_to_html') . '/tests/files';
  }

}
