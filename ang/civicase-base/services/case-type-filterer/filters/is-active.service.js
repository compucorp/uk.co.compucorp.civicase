(function (_, angular) {
  var module = angular.module('civicase-base');

  module.service('IsActiveCaseTypeFilter', IsActiveCaseTypeFilter);

  /**
   * IsActiveCaseTypeFilter Case Type Filter service.
   *
   * @param {Function} isTruthy service to compare truthy values.
   */
  function IsActiveCaseTypeFilter (isTruthy) {
    this.run = run;
    this.shouldRun = shouldRun;

    /**
     * @param {object} caseType case type to match.
     * @param {object} caseTypeFilters list of parameters to use for matching.
     * @returns {boolean} true when active
     */
    function run (caseType, caseTypeFilters) {
      return isTruthy(caseType.is_active) === isTruthy(caseTypeFilters.is_active);
    }

    /**
     * @param {object} caseTypeFilters list of parameters to use for matching.
     * @returns {boolean} true when the `is_active` filter has a value.
     */
    function shouldRun (caseTypeFilters) {
      return typeof caseTypeFilters.is_active !== 'undefined';
    }
  }
})(CRM._, angular);
