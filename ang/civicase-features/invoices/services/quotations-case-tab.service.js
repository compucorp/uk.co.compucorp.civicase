(function (angular, $, _) {
  var module = angular.module('civicase');

  module.service('InvoicesCaseTab', InvoicesCaseTab);

  /**
   * Invoices Case Tab service.
   */
  function InvoicesCaseTab () {
    /**
     * @returns {string} Returns tab content HTMl template url.
     */
    this.activeTabContentUrl = function () {
      return '~/civicase-features/invoices/directives/invoices-case-tab-content.html';
    };
  }
})(angular, CRM.$, CRM._);
