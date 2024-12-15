(function($, Drupal, once) {
  Drupal.behaviors.docxToHtml = {
    attach: function(context, settings) {
      once('docxToHtml', '#document', context).forEach(function(element) {
        element.addEventListener("change", handleFileSelect, false);
      });

      once('docxToHtml', '#copy-button', context).forEach(function(element) {
        element.addEventListener('click', function(e) {
          e.preventDefault();
          copyToClipboard();
        });
      });

      function handleFileSelect(event) {
        var file = event.target.files[0];
        if (!file || file.type !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
          displayMessage(Drupal.t('Invalid file type. Please choose a DOCX file.'), 'error');
          return;
        }

        const messageStart = Drupal.t('Start converting @name file.', { '@name': file.name });
        displayMessage(messageStart, 'status', true);
        readFileInputEventAsArrayBuffer(event, function(arrayBuffer) {
          mammoth.convertToHtml({ arrayBuffer: arrayBuffer })
            .then(displayResult)
            .catch(function(error) {
              console.error(error);
            });
        });
      }

      function displayMessage(message, messageType, clear = false) {
        const messages = new Drupal.Message();
        if (clear) {
          messages.clear();
        }
        var safeMessage = Drupal.checkPlain(message);
        messages.add(safeMessage, { type: messageType });
      }

      function displayResult(result) {
        document.getElementById("output").innerHTML = result.value;
        // Show message returned.
        result.messages.map(function(message) {
          displayMessage(Drupal.t(message.message), 'warning');
        });

        $('#copy-button').show(); // Show the copy button after conversion
        $('#output').show(); // Show the output
        displayMessage(Drupal.t('Conversion is completed.'), 'status');
      }

      function readFileInputEventAsArrayBuffer(event, callback) {
        var file = event.target.files[0];

        var reader = new FileReader();

        reader.onload = function(loadEvent) {
          var arrayBuffer = loadEvent.target.result;
          callback(arrayBuffer);
        };

        reader.readAsArrayBuffer(file);
      }

      function copyToClipboard() {
        var outputElement = document.getElementById('output');
        var range = document.createRange();
        range.selectNodeContents(outputElement);
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        try {
          document.execCommand('copy');
          displayMessage(Drupal.t('HTML code copied to clipboard!'), 'status');
        } catch (err) {
          displayMessage(Drupal.t('Unable to copy HTML code.'), 'error');
        }

        // Remove the selection
        selection.removeAllRanges();
      }
    }
  };
})(jQuery, Drupal, once);
