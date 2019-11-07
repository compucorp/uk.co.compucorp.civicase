(function (angular, $, _, CRM) {
  var module = angular.module('civicase-base');

  module.service('ActivityCategory', ActivityCategory);

  /**
   * Activity Category Service
   */
  function ActivityCategory () {
    this.getAll = function () {
      return CRM['civicase-base'].activityCategories;
    };
  }
})(angular, CRM.$, CRM._, CRM);
