(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('CaseStatus', CaseStatus);

  /**
   * Case Status Service
   */
  function CaseStatus () {
    this.getAll = function () {
      return CRM['civicase-base'].caseStatuses;
    };
  }
})(angular, CRM.$, CRM._, CRM);
