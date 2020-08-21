(function (angular, $) {
  var module = angular.module('civicase');

  module.service('removeDatePickerHrefs', function ($timeout) {
    var HREFS_SELECTOR = '[data-handler="selectDay"] a';

    return datepickerBeforeShow;

    /**
     * Removes HREF attribtues from anchor elements defined in calendar inputs.
     * This is done to avoid switching the angular route's path unintentionally.
     *
     * @param {object} inputElement A reference to the date picker's input element.
     * @param {object} uiObject The jQuery UI data object.
     */
    function datepickerBeforeShow (inputElement, uiObject) {
      setTimeout(function () {
        uiObject.dpDiv
          .find(HREFS_SELECTOR)
          .removeAttr('href');
      });
    }
  });
})(angular, CRM.$);
