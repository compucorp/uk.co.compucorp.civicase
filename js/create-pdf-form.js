(function ($, _) {
  $(document).one('crmLoad', function () {
    var $dialog;

    (function init () {
      var $form = $('.CRM_Contact_Form_Task_PDF');

      $('body').off('submit', $form);
      $('body').on('submit', $form, openFileNamePopup);

      // Update only the data-identifier on the button that already has it
      $('button[data-identifier="buttons[_qf_PDF_submit_preview]"]').attr('data-identifier', '_qf_PDF_submit_preview');

      // Update the name attribute on both buttons
      $('button[name="buttons[_qf_PDF_submit_preview]"]').attr('name', '_qf_PDF_submit_preview');
    })();

    /**
     * Open popup to ask filename
     *
     * @param {object} event event
     */
    function openFileNamePopup (event) {
      if (!isFormValid()) {
        return;
      }

      var $form = $(event.target);
      var $clickedButtonIdentifier = $(document.activeElement).data('identifier');
      var isDownloadDocumentButtonClicked = $clickedButtonIdentifier === 'buttons[_qf_PDF_upload]';

      if (!isDownloadDocumentButtonClicked) {
        return;
      }

      event.preventDefault();

      $dialog = $('<div class="civicase__pdf-filename-dialog"></div>')
        .html(getPopupMarkUp($form))
        .dialog(getPopupSettings());

      $('form', $dialog).crmValidate();
    }

    /**
     * Checks whether the form has errors or not.
     *
     * @returns {boolean} form validity.
     */
    function isFormValid () {
      return $('.CRM_Contact_Form_Task_PDF').valid();
    }

    /**
     * Get html markup for the filename popup
     *
     * @param {object} $form form element
     *
     * @returns {string} markup
     */
    function getPopupMarkUp ($form) {
      var subject = $form.find('#subject').val();

      return '<form id="civicase__pdf-filename-form">' +
        '<div class="crm-form-block crm-block crm-contact-task-pdf-form-block">' +
          '<table class="form-layout-compressed">' +
             '<tbody>' +
                '<tr>' +
                  '<td class="label-left">' +
                    '<label for="template">Select a filename</label>' +
                  '</td>' +
                '</tr>' +
                '<tr>' +
                  '<td>' +
                    '<input type="text" id="pdf_filename" pattern="[a-zA-Z0-9-_. ]+" class="huge crm-form-text required" value="' + subject + '">' +
                  '</td>' +
                '</tr>' +
              '</tbody>' +
            '</table>' +
          '</div>' +
        '</form>';
    }

    /**
     * Get settings for the filename popup
     *
     * @returns {object} settings object
     */
    function getPopupSettings () {
      return {
        title: ts('Download Document'),
        width: 'auto',
        height: 'auto',
        buttons: [
          {
            text: 'Download Document',
            click: function () {
              if (!$('#civicase__pdf-filename-form').valid()) {
                return;
              }

              $('[name="pdf_file_name"]').val($('#pdf_filename').val());

              $dialog.remove();
              $('[data-identifier="buttons[_qf_PDF_upload]"]').trigger('click');

              closePDFPopup();
            }
          },
          {
            text: 'Cancel',
            click: function () {
              $dialog.remove();
            }
          }
        ]
      };
    }

    /**
     * Close the PDF popup, and trigger `crmPopupFormSuccess` event.
     */
    function closePDFPopup () {
      var $form = $('.CRM_Contact_Form_Task_PDF');
      var popup = $form.closest('.ui-dialog-content');

      // For PDF form, the PHP code does a `CRM_Utils_System::civiExit();`,
      // and for that reason there is no way to capture the `success` event
      // after the api call is finished, hence using timeout of 1000 ms
      setTimeout(function () {
        popup.trigger('crmPopupFormSuccess'); // to refresh the page.
        popup.dialog('close');
      }, 1000);
    }
  });
})(CRM.$, CRM._);
