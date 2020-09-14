(function (angular, $) {
  var module = angular.module('civicase-base');

  module.directive('civicaseInlineDatepicker', function ($timeout,
    removeDatePickerHrefs) {
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
      var model = controllers[0];

      (function init () {
        element.wrap(DATEPICKER_WRAPPER);
        element.attr('placeholder', attributes.placeholder || '');
        model.$formatters.push(modelDateFormatter);
        model.$parsers.push(inputDateParser);
        model.$validators.isValidDate = isValidDate;

        element.datepicker({
          beforeShow: removeDatePickerHrefs,
          onChangeMonthYear: removeDatePickerHrefs
        });
      })();

      /**
       * @param {string} modelValue the value stored in the model.
       * @returns {string|undefined} it returns the date in a DD/MM/YYYY format,
       *   if defined. This makes the value stored in the model more user
       *   friendly.
       */
      function modelDateFormatter (modelValue) {
        if (modelValue) {
          return moment(modelValue).format('DD/MM/YYYY');
        }
      }

      /**
       * @param {string} inputValue the value stored in the input element.
       * @returns {string|undefined} it returns the date in a YYYY-MM-DD format,
       *   if defined. This is useful when converting the human readable format
       *   from the input to one that can be stored in the model and passed down
       *   to APIs.
       */
      function inputDateParser (inputValue) {
        if (inputValue) {
          return moment(inputValue, 'DD/MM/YYYY', true).format('YYYY-MM-DD');
        }
      }

      /**
       * Checks if the given value is a valid date.
       *
       * @param {string} inputValue input's date value.
       * @returns {boolean} true when the date is in a valid format or no value
       *   is provided.
       */
      function isValidDate (inputValue) {
        return !inputValue || moment(inputValue).isValid();
      }
    }
  });
})(angular, CRM.$);
