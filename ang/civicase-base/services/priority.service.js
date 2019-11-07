(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('Priority', Priority);

  /**
   * Priority Service
   */
  function Priority () {
    this.getAll = function () {
      return CRM['civicase-base'].priority;
    };
  }
})(angular, CRM.$, CRM._, CRM);
