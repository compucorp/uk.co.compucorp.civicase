(function ($, _) {
  var caseExtends = (CRM.vars.civicase && CRM.vars.civicase.caseCustomGroupExtends) || [];

  /**
   * Shows "Maximum number of multiple records" only when the custom field set
   * allows multiple records and does not extend a Case, and empties it for
   * Cases.
   *
   * @param {object} $form the Custom Field Set form
   */
  function toggleMaxMultiple ($form) {
    var extendsValue = $('[name=extends]', $form).val();
    var $isMultiple = $('input#is_multiple', $form);
    var isMultiple = $isMultiple.is(':checked') ||
      ($isMultiple.attr('type') === 'hidden' && $isMultiple.val() === '1');
    var extendsCase = _.includes(caseExtends, extendsValue);

    if (extendsCase) {
      $('input[name=max_multiple]', $form).val('');
    }

    $('tr.field-max_multiple', $form).toggle(isMultiple && !extendsCase);
  }

  $(document).on('crmLoad', function (event) {
    var $form = $(event.target).find('form.CRM_Custom_Form_Group').addBack('form.CRM_Custom_Form_Group');

    if (!$form.length || $form.data('civicaseMaxMultiple')) {
      return;
    }
    $form.data('civicaseMaxMultiple', true);

    $form.on('change', 'input[name=extends], input#is_multiple', function () {
      toggleMaxMultiple($form);
    });

    setTimeout(function () {
      toggleMaxMultiple($form);
    }, 0);
  });
})(CRM.$, CRM._);
