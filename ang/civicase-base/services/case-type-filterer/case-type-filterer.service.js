(function (_, angular) {
  var module = angular.module('civicase-base');

  module.service('CaseTypeFilterer', CaseTypeFilterer);

  /**
   * CaseTypeFilterer
   *
   * @param {object} BelongsToCategoryCaseTypeFilter case type filter reference.
   * @param {object} CaseType case type service reference.
   * @param {object} HasIdCaseTypeFilter case type filter reference.
   * @param {object} IsActiveCaseTypeFilter case type filter reference.
   * @param {object} IsIncludedInListOfIdsCaseTypeFilter case type filter reference.
   */
  function CaseTypeFilterer (BelongsToCategoryCaseTypeFilter, CaseType,
    HasIdCaseTypeFilter, IsActiveCaseTypeFilter, IsIncludedInListOfIdsCaseTypeFilter) {
    var DEFAULT_FILTER_VALUES = {
      is_active: true
    };
    var listOfFilters = [
      IsActiveCaseTypeFilter,
      BelongsToCategoryCaseTypeFilter,
      IsIncludedInListOfIdsCaseTypeFilter,
      HasIdCaseTypeFilter
    ];

    this.filter = filter;

    /**
     * Returns a filtered list of case types. The case types are matched against
     * a list of filters. These filters are selected depending on the parameters
     * sent through `caseTypeFilters`.
     *
     * @param {object} userFilterValues parameters to use for filtering the case types.
     * @returns {object[]} a list of case types.
     */
    function filter (userFilterValues) {
      var filterValues = _.defaults({}, DEFAULT_FILTER_VALUES, userFilterValues);
      var caseTypes = _.values(CaseType.getAll({ includeInactive: true }));
      var listOfFiltersToRun = _.filter(listOfFilters, function (filter) {
        return filter.shouldRun(filterValues);
      });

      return _.filter(caseTypes, function (caseType) {
        return _.every(listOfFiltersToRun, function (filter) {
          return filter.run(caseType, filterValues);
        });
      });
    }
  }
})(CRM._, angular);
