<?php

namespace Drupal\Tests\docx_to_html\FunctionalJavascript;

/**
 * Tests the DOCX to HTML conversion functionality.
 *
 * @group docx_to_html
 */
class DocxToHtmlTest extends DocxToHtmlTestBase {

  /**
   * Tests the DOCX to HTML conversion.
   */
  public function testDocxToHtmlConversion() {
    // Set up a user with permissions to access the custom page.
    $user = $this->drupalCreateUser(['access docx to html converter']);
    $this->drupalLogin($user);

    // Visit the DOCX to HTML conversion page.
    $this->drupalGet('/docx-to-html');

    // Check that the file input and convert button are present.
    $this->assertSession()->fieldExists('document');

    // Test with a valid DOCX file.
    $this->uploadFileAndWaitForConversion('/docx_to_html_test.docx');
    // The converted HTML content.
    $output = $this->getSession()->getPage()->find('css', '#output')->getHtml();

    // Verify the output and copy button are now visible.
    $this->assertTrue($this->getSession()->getPage()->find('css', '#output')->isVisible(), "Output element is not visible.");
    $this->assertTrue($this->getSession()->getPage()->find('css', '#copy-button')->isVisible(), "Copy button is not visible.");
    // Verify the output is not empty.
    $this->assertNotEmpty($output, 'The output HTML should not be empty after conversion.');

    // Click the "Copy the HTML" button and verify the clipboard content.
    $this->getSession()->getPage()->find('css', '#copy-button')->click();
    $this->assertSession()->pageTextContains('HTML code copied to clipboard!');
  }

  /**
   * Tests access to the DOCX to HTML conversion page for anonymous users.
   */
  public function testAccessAsAnonymous() {
    // Logout to ensure we are anonymous.
    $this->drupalLogout();

    // Attempt to visit the DOCX to HTML conversion page.
    $this->drupalGet('/docx-to-html');

    // Verify that the access is denied
    // by checking for the access denied message.
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');
  }

}
