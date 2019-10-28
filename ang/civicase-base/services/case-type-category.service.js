(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('CaseTypeCategory', CaseTypeCategory);

  /**
   * CaseTypeCategory Service
   */
  function CaseTypeCategory () {
    this.getAll = function () {
      return CRM['civicase-base'].caseTypeCategories;
    };
  }
})(angular, CRM.$, CRM._, CRM);
