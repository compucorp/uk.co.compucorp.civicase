(function (angular, $) {
  var module = angular.module('civicase-base');

  module.directive('civicaseInlineDatepicker', function ($timeout,
    dateInputFormatValue, removeDatePickerHrefs) {
    return {
      restrict: 'A',
      link: civicaseInlineDatepickerLink,
      require: ['ngModel']
    };

    /**
     * Link function for inline datepicker directive.
     *
     * @param {object} scope scope of the directive
     * @param {object} element element
     * @param {object} attributes element attributes
     * @param {object[]} controllers list of required controllers
     */
    function civicaseInlineDatepickerLink (scope, element, attributes, controllers) {
      var DATEPICKER_WRAPPER = '<div class="civicase-inline-datepicker__wrapper"></div>';
      var API_DATE_FORMAT = 'yy-mm-dd';
      var model = controllers[0];

      (function init () {
        element.wrap(DATEPICKER_WRAPPER);
        element.attr('placeholder', attributes.placeholder || '');
        model.$formatters.push(modelDateFormatter);
        model.$parsers.push(inputDateParser);
        model.$validators.isValidDate = isValidDate;

        element.datepicker({
          beforeShow: removeDatePickerHrefs,
          dateFormat: dateInputFormatValue,
          onChangeMonthYear: removeDatePickerHrefs
        });
      })();

      /**
       * @param {string} modelValue the value stored in the model.
       * @returns {string|undefined} it returns the date in the date format
       *   configured by CiviCRM. This makes the value stored in the model more
       *   user friendly. If no value is provided then it returns undefined.
       */
      function modelDateFormatter (modelValue) {
        if (modelValue) {
          return $.datepicker.formatDate(
            dateInputFormatValue,
            $.datepicker.parseDate(API_DATE_FORMAT, modelValue)
          );
        }
      }

      /**
       * @param {string} inputValue the value stored in the input element.
       * @returns {string|undefined} it returns the date in a year-month-day
       *   format, if defined. This is useful when converting the human readable
       *   format from the input to one that can be stored in the model and
       *   passed down to APIs.
       */
      function inputDateParser (inputValue) {
        if (inputValue) {
          try {
            return $.datepicker.formatDate(
              API_DATE_FORMAT,
              $.datepicker.parseDate(dateInputFormatValue, inputValue)
            );
          } catch (exception) {
            model.$setValidity('isValidDate', false);
          }
        }
      }

      /**
       * Checks if the given value is a valid date.
       *
       * @param {string} modelValue input's date value.
       * @returns {boolean} true when the date is in a valid format or no value
       *   is provided.
       */
      function isValidDate (modelValue) {
        if (!modelValue) {
          return true;
        }

        try {
          $.datepicker.parseDate(API_DATE_FORMAT, modelValue);

          return true;
        } catch (exception) {
          return false;
        }
      }
    }
  });
})(angular, CRM.$);
