(function (_, angular) {
  var module = angular.module('civicase-base');

  module.service('IsActiveCaseTypeFilter', IsActiveCaseTypeFilter);

  /**
   * IsActiveCaseTypeFilter Case Type Filter service.
   */
  function IsActiveCaseTypeFilter () {
    this.run = run;
    this.shouldRun = shouldRun;

    /**
     * @param {object} caseType case type to match.
     * @param {object} caseTypeFilters list of parameters to use for matching.
     * @returns {boolean} true when active
     */
    function run (caseType, caseTypeFilters) {
      return caseType.is_active === caseTypeFilters.is_active;
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
