(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('CaseType', CaseType);

  /**
   * CaseType Service
   */
  function CaseType () {
    this.getAll = function () {
      return CRM['civicase-base'].caseTypes;
    };
  }
})(angular, CRM.$, CRM._, CRM);
