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

  module.factory('civicaseAfformErrors', civicaseAfformErrors);

  /**
   * Explains which fields of an afform are invalid, and why.
   *
   * Afform's own submit reports any invalid form as "Please fill all
   * required fields.", even when the problem is e.g. a malformed URL.
   *
   * @returns {object} the service
   */
  function civicaseAfformErrors () {
    return {
      describe: describeErrors,
      show: showErrors
    };

    /**
     * Flags the invalid fields and lists them, with the reason, in an alert.
     *
     * @param {object} $form the af-form element
     */
    function showErrors ($form) {
      markInvalidFields($form);
      CRM.alert(describeErrors($form), ts('Please correct the following'), 'error');
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
     * A field in a repeatable section is named with its entry, e.g.
     * "Title (entry 2)", when the section has more than one entry.
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
        var entryNumber = getEntryNumber($field);
        var reason = _.find(ERROR_MESSAGES, function (entry) {
          return $field.find('.ng-invalid-' + entry[0]).length > 0;
        });

        if (entryNumber) {
          label = ts('%1 (entry %2)', { 1: label, 2: entryNumber });
        }
        items.push('<li><strong>' + _.escape(label) + '</strong> ' +
          (reason ? reason[1]() : ts('is not valid.')) + '</li>');
      });

      // One error per line also when CRM.alert falls back to the browser's
      // alert(), which shows the text without the markup (pages without
      // CiviCRM's notifications, e.g. public forms in the site's theme).
      return items.length
        ? '<ul>' + items.join('\n') + '</ul>'
        : ts('Please check the form for errors.');
    }

    /**
     * The position of a field's entry in a repeatable section, from 1.
     *
     * @param {object} $field the af-field element
     * @returns {number|null} the entry number, or null when the field is not
     *   in a repeatable section or the section has a single entry
     */
    function getEntryNumber ($field) {
      var $item = $field.closest('[af-repeat-item]');
      var $items = $item.parent().children('[af-repeat-item]');

      return $items.length > 1 ? $items.index($item) + 1 : null;
    }
  }

  module.directive('civicaseRepeatableCustomDataSubmit', function (civicaseAfformErrors) {
    return {
      restrict: 'A',
      link: civicaseRepeatableCustomDataSubmitLink
    };

    /**
     * Saves the form, or explains which fields are invalid and why.
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
          civicaseAfformErrors.show(element.closest('af-form'));
        });
      });
    }
  });

  module.directive('afForm', function (civicaseAfformErrors) {
    return {
      restrict: 'E',
      require: 'afForm',
      link: afFormLink
    };

    /**
     * Gives Form Builder forms with repeatable Case groups the same messages.
     *
     * @param {object} scope the form's scope
     * @param {object} element the af-form element
     * @param {object} attrs attributes
     * @param {object} afForm afform core's form controller
     */
    function afFormLink (scope, element, attrs, afForm) {
      var submit = afForm.submit;

      afForm.submit = function () {
        if (afForm.ngForm && !afForm.ngForm.$valid && hasRepeatableCaseGroup(element)) {
          civicaseAfformErrors.show(element);
          return;
        }

        return submit.apply(this, arguments);
      };
    }

    /**
     * Whether a form has a repeatable Case group, as an entity or a join.
     *
     * @param {object} $form the af-form element
     * @returns {boolean} true if it has one
     */
    function hasRepeatableCaseGroup ($form) {
      var used = $form.find('[af-join]').map(function () {
        return $(this).attr('af-join');
      }).get().concat($form.find('af-entity').map(function () {
        return $(this).attr('type');
      }).get());

      return _.some(used, isRepeatableCaseEntity);
    }
  });

  module.directive('afField', function () {
    return {
      restrict: 'E',
      require: ['afField', '?^^afJoin'],
      link: { pre: afFieldPreLink }
    };

    /**
     * Names the join in the autocomplete requests of a repeatable group field.
     *
     * Runs before the field's template, so the autocomplete is created with
     * the wrapped function.
     *
     * @param {object} scope the field's scope
     * @param {object} element the af-field element
     * @param {object} attrs attributes
     * @param {Array} ctrls afform core's field controller and its join's
     */
    function afFieldPreLink (scope, element, attrs, ctrls) {
      var afField = ctrls[0];
      var afJoin = ctrls[1];

      if (!afJoin || !afField.getAutocompleteParams || !isRepeatableCaseEntity(afJoin.entity)) {
        return;
      }
      var getAutocompleteParams = afField.getAutocompleteParams;

      afField.getAutocompleteParams = function () {
        var params = getAutocompleteParams.apply(this, arguments);

        if (!params) {
          return params;
        }
        var parts = (params.fieldName || '').split(':');

        if (parts.length === 2 && parts[0].indexOf('+') === -1) {
          params.fieldName = parts[0] + '+' + afJoin.entity + ':' + parts[1];
        }

        return params;
      };
    }
  });

  /**
   * Whether an APIv4 entity is a repeatable Case custom group.
   *
   * @param {string} entityName e.g. "Custom_publications"
   * @returns {boolean} true if it is
   */
  function isRepeatableCaseEntity (entityName) {
    var settings = CRM['civicase-repeatable-custom-data-form'] || {};

    return _.includes(settings.repeatableCaseEntities || [], entityName);
  }
})(angular, CRM.$, CRM._, CRM, CRM.ts('uk.co.compucorp.civicase'));
