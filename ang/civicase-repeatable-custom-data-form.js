(function (angular, $, _, CRM, ts) {
  var module = angular.module('civicase-repeatable-custom-data-form', []);
  var STYLESHEET_ID = 'civicase-repeatable-custom-data-form-css';

  if (!document.getElementById(STYLESHEET_ID)) {
    $('<link rel="stylesheet" type="text/css">')
      .attr('id', STYLESHEET_ID)
      .attr('href', CRM.resourceUrls['uk.co.compucorp.civicase'] +
        '/ang/civicase-repeatable-custom-data-form/form.css')
      .appendTo('head');
  }

  var ERROR_MESSAGES = [
    ['required', function () { return ts('is required.'); }],
    ['url', function () { return ts('must be a valid URL, e.g. https://example.org.'); }],
    ['email', function () { return ts('must be a valid email address.'); }],
    ['number', function () { return ts('must be a number.'); }],
    ['min', function () { return ts('is below the minimum allowed value.'); }],
    ['max', function () { return ts('is above the maximum allowed value.'); }],
    ['maxlength', function () { return ts('is too long.'); }],
    ['minlength', function () { return ts('is too short.'); }],
    ['pattern', function () { return ts('is not in the expected format.'); }]
  ];

  module.directive('civicaseRepeatableCustomDataSubmit', function () {
    return {
      restrict: 'A',
      link: civicaseRepeatableCustomDataSubmitLink
    };

    /**
     * Saves the form, or explains which fields are invalid and why.
     *
     * Afform's own submit reports any invalid form as "Please fill all
     * required fields.", even when the problem is e.g. a malformed URL. This
     * validates first with a message per field, and only hands over to
     * afform's submit once the form is valid (it still does its own checks,
     * e.g. required file uploads, and the server validates again).
     *
     * @param {object} scope the button's scope
     * @param {object} element the Save button
     * @param {object} attrs attributes; the directive's value is the afform
     *   controller expression (e.g. "afform")
     */
    function civicaseRepeatableCustomDataSubmitLink (scope, element, attrs) {
      element.on('click', function (event) {
        event.preventDefault();
        scope.$apply(function () {
          var afform = scope.$eval(attrs.civicaseRepeatableCustomDataSubmit);
          var form = afform && afform.ngForm;

          if (!form || form.$valid) {
            afform && afform.submit();
            return;
          }
          markInvalidFields(element.closest('af-form'));
          CRM.alert(describeErrors(element.closest('af-form')), ts('Please correct the following'), 'error');
        });
      });
    }

    /**
     * Flags af-fields that contain an invalid input so they can be styled.
     *
     * @param {object} $form the af-form element
     */
    function markInvalidFields ($form) {
      $form.find('af-field').each(function () {
        $(this).toggleClass('has-error', $(this).find('.ng-invalid').length > 0);
      });
      // Clear a field's flag as soon as it is edited.
      $form.off('.civicaseValidation')
        .on('input.civicaseValidation change.civicaseValidation', 'af-field.has-error', function () {
          $(this).removeClass('has-error');
        });
    }

    /**
     * Builds a list of "<Field label> <reason>" for every invalid field.
     *
     * @param {object} $form the af-form element
     * @returns {string} HTML list of errors
     */
    function describeErrors ($form) {
      var items = [];

      $form.find('af-field').each(function () {
        var $field = $(this);
        var $invalid = $field.find('.ng-invalid').first();

        if (!$invalid.length) {
          return;
        }

        var label = $.trim($field.find('.crm-af-field-label').first().text())
          .replace(/\s*\*$/, '') || ts('A field');
        var reason = _.find(ERROR_MESSAGES, function (entry) {
          return $field.find('.ng-invalid-' + entry[0]).length > 0;
        });

        items.push('<li><strong>' + _.escape(label) + '</strong> ' +
          (reason ? reason[1]() : ts('is not valid.')) + '</li>');
      });

      return items.length
        ? '<ul>' + items.join('') + '</ul>'
        : ts('Please check the form for errors.');
    }
  });
})(angular, CRM.$, CRM._, CRM, CRM.ts('uk.co.compucorp.civicase'));
