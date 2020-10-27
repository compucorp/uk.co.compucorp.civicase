(function ($, _, caseSettings) {
  var $recipientFields;
  var caseContacts = JSON.parse(caseSettings.case_contacts);

  // Without the timeout the CC and BCC fields can't be properly replaced
  setTimeout(function init () {
    initSelectors();
    addNewRecipientDropdowns();
  });

  /**
   * Replaces the "To", "CC", "BCC" dropdowns with new ones that only contain
   * case contacts.
   *
   * The "To" field values must be stored in a `123::contact@example.com` format,
   * where `123` is the contact's ID. The "CC" and "BCC" field values must contain
   * the contact ID only.
   */
  function addNewRecipientDropdowns () {
    $recipientFields.each(function () {
      var $field = $(this);
      var isToField = $field.attr('name') === 'to';
      var caseContactSelect2Options = isToField
        ? getCaseContactOptions({ idFieldName: 'value' })
        : getCaseContactOptions({ idFieldName: 'contact_id' });

      $field.crmSelect2({
        multiple: true,
        data: caseContactSelect2Options
      });
    });
  }

  /**
   * @param {object} options list of options.
   * @param {string} options.idFieldName The contact's field name to use as the
   * option's ID.
   * @returns {object[]} The list of case contacts as expected by the select2
   * dropdowns.
   */
  function getCaseContactOptions (options) {
    return caseContacts.map(function (caseContact) {
      return {
        id: caseContact[options.idFieldName],
        text: _.template('<%= display_name %> <<%= email %>>')(caseContact)
      };
    });
  }

  /**
   * Populates the Recipient form rows selectors.
   */
  function initSelectors () {
    var recipientFieldRowsSelectors = [
      '.crm-contactEmail-form-block-recipient input[name]',
      '.crm-contactEmail-form-block-cc_id input[name]',
      '.crm-contactEmail-form-block-bcc_id input[name]'
    ];
    $recipientFields = $(recipientFieldRowsSelectors.join(','));
  }
})(CRM.$, CRM._, CRM['civicase-base']);
