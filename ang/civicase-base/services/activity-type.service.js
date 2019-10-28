(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('ActivityType', ActivityType);

  /**
   * Activity Types Service
   */
  function ActivityType () {
    this.getAll = function () {
      return CRM['civicase-base'].activityTypes;
    };
  }
})(angular, CRM.$, CRM._, CRM);
