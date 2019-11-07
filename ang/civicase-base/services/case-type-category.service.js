(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('CaseTypeCategory', CaseTypeCategory);

  /**
   * CaseTypeCategory Service
   */
  function CaseTypeCategory () {
    var allCaseTypeCategories = CRM['civicase-base'].caseTypeCategories;

    this.getAll = function () {
      return allCaseTypeCategories;
    };

    /**
     * Find case type category by value
     *
     * @param {string} name case type category name
     * @returns {object} case type category object
     */
    this.findByName = function (name) {
      return _.find(allCaseTypeCategories, function (category) {
        return category.name === 'awards';
      });
    };
  }
})(angular, CRM.$, CRM._, CRM);
