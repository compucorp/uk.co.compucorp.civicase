(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('ActivityStatus', ActivityStatus);

  /**
   * Activity Status Service
   */
  function ActivityStatus () {
    this.getAll = function () {
      return CRM['civicase-base'].activityStatuses;
    };
  }
})(angular, CRM.$, CRM._, CRM);
