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
   */
  function addNewRecipientDropdowns () {
    var caseContactSelect2Options = getCaseContactSelect2Options();

    $recipientFields.crmSelect2({
      multiple: true,
      data: caseContactSelect2Options
    });
  }

  /**
   * @returns {object[]} The list of case contacts as expected by the select2
   * dropdowns.
   */
  function getCaseContactSelect2Options () {
    return caseContacts.map(function (caseContact) {
      return {
        id: caseContact.value,
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
