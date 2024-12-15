<?php

namespace Drupal\Tests\docx_to_html\FunctionalJavascript;

/**
 * Security tests for the DOCX to HTML converter.
 *
 * @group docx_to_html
 */
class DocxToHtmlSecurityTest extends DocxToHtmlTestBase {

  /**
   * Tests the DOCX to HTML conversion process.
   */
  public function testDocxToHtmlConversion() {
    // Assert initial page state.
    $this->assertInitialPageState();

    // Test with an invalid file type.
    $this->uploadFileAndWaitForConversion('/invalid_test.txt');
    $this->assertSession()->pageTextContains('Invalid file type. Please choose a DOCX file.');

    // Test XSS code within the docx file.
    $this->uploadFileAndWaitForConversion('/xss_test.docx');
    // Verify the converted HTML content.
    $output = $this->getSession()->getPage()->find('css', '#output')->getHtml();
    $this->assertNotEmpty($output, 'The converted HTML should not be empty.');

    // Check that the output does not contain any executable script.
    $this->assertDoesNotMatchRegularExpression('/<script[^>]*>/', $output, 'The converted HTML contains script tags.');
    $this->assertDoesNotMatchRegularExpression('/<iframe[^>]*>/', $output, 'The converted HTML contains iframe tags.');
    $this->assertDoesNotMatchRegularExpression('/<a[^>]*>/', $output, 'The converted HTML contains a tag with base64 encoded data URLs.');
    // Check that no alert was executed.
    $alertCalls = $this->getSession()->evaluateScript('window.alert.calls');
    $this->assertEmpty($alertCalls, 'An alert was executed.');
  }

}
