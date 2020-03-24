(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.provider('CaseType', CaseTypeServiceProvider);

  /**
   * CaseType Service provider
   */
  function CaseTypeServiceProvider () {
    var caseTypes = CRM['civicase-base'].caseTypes;
    var DEFAULT_GET_ALL_OPTIONS = {
      includeInactive: false
    };

    this.$get = $get;
    this.getAll = getAll;
    this.getByCategory = getByCategory;
    this.getTitlesForNames = getTitlesForNames;

    /**
     * Returns an instance of the case type service.
     *
     * @param {object} DashboardCaseTypeItems the dashboard case type items.
     * @returns {object} the case type service.
     */
    function $get (DashboardCaseTypeItems) {
      return {
        getAll: getAll,
        getByCategory: getByCategory,
        getItemsForCaseType: getItemsForCaseType,
        getTitlesForNames: getTitlesForNames
      };

      /**
       * Returns the Dashboard items for the given case type.
       *
       * @param {string} caseTypeName the name of the case type to get the buttons for.
       * @returns {object[]} a list of buttons.
       */
      function getItemsForCaseType (caseTypeName) {
        return DashboardCaseTypeItems[caseTypeName] || [];
      }
    }

    /**
     * @param {object} options configuration options to use for filtering
     *   the case types.
     * @returns {object[]} a list of case types.
     */
    function getAll (options) {
      options = _.defaults({}, options, DEFAULT_GET_ALL_OPTIONS);

      return options.includeInactive
        ? caseTypes
        : getAllActive();
    }

    /**
     * @returns {object[]} all active case types.
     */
    function getAllActive () {
      return _.pick(caseTypes, _.matches({ is_active: '1' }));
    }

    /**
     * Returns only the case types belonging to the given category.
     *
     * @param {number} categoryValue the case type category value.
     * @returns {object[]} a list of case types.
     */
    function getByCategory (categoryValue) {
      return _.filter(caseTypes, function (caseType) {
        return caseType.case_type_category === categoryValue;
      });
    }

    /**
     * Returns a list of case type titles for the given names.
     *
     * @param {string[]} caseTypeNames the case type names.
     * @returns {string[]} a list of case type titles.
     */
    function getTitlesForNames (caseTypeNames) {
      return _.map(caseTypeNames, function (caseTypeName) {
        return _.findWhere(caseTypes, { name: caseTypeName }).title;
      });
    }
  }
})(angular, CRM.$, CRM._, CRM);
