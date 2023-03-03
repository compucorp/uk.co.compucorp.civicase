(function (angular, $, _) {
  var module = angular.module('civicase');

  module.service('QuotationsCaseTab', QuotationsCaseTab);

  /**
   * Quotations Case Tab service.
   */
  function QuotationsCaseTab () {
    /**
     * @returns {string} Returns tab content HTMl template url.
     */
    this.activeTabContentUrl = function () {
      return '~/civicase-features/quotations/directives/quotations-case-tab-content.html';
    };
  }
})(angular, CRM.$, CRM._);
