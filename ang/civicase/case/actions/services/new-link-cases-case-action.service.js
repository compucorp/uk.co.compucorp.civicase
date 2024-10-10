(function (angular, $, _) {
  var module = angular.module('civicase');

  module.service('NewLinkCasesCaseAction', NewLinkCasesCaseAction);

  /**
   * Create and Link Case Action service
   *
   * @param {object} $q $q service
   * @param {object} ActivityType ActivityType
   * @param {string} currentCaseCategory current case category
   */
  function NewLinkCasesCaseAction ($q, ActivityType, currentCaseCategory) {
    /**
     * Click event handler for the Action
     *
     * @param {object} cases cases
     *
     * @returns {Promise} promise which resolves to the path for the popup
     */
    this.doAction = function (cases) {
      var currentCase = cases[0];
      var activityTypes = ActivityType.getAll(true);

      var link = {
        path: 'civicrm/case/add',
        query: {
          action: 'add',
          reset: 1,
          atype: _.findKey(activityTypes, { name: 'Open Case' }),
          linkToCaseId: currentCase.id,
          context: 'standalone',
          case_type_category: currentCaseCategory
        }
      };

      return $q.resolve(link);
    };
  }
})(angular, CRM.$, CRM._);
