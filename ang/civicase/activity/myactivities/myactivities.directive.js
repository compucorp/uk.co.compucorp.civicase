(function (angular, _) {
  var module = angular.module('civicase');

  module.directive('civicaseMyActivities', function () {
    return {
      restrict: 'EA',
      controller: 'CivicaseMyActivitiesController',
      templateUrl: '~/civicase/activity/myactivities/myactivities.directive.html',
      scope: {}
    };
  });

  module.controller('CivicaseMyActivitiesController', CivicaseMyActivitiesController);

  /**
   * @param {object} $scope the controller scope
   * @param {object} Contact Contact global service
   * @param {object} ActivityStatus Activity Status service
   */
  function CivicaseMyActivitiesController ($scope, Contact, ActivityStatus) {
    var INCOMPLETE_ACTIVITY_STATUS_CATEGORY = '0';

    $scope.displayOptions = { include_case: true };
    $scope.filters = {
      $contact_id: Contact.getCurrentContactID(),
      '@involvingContact': 'myActivities',
      status_id: getIncompleteActivityStatusIDs()
    };

    /**
     * Get Activity Status IDs where status category is 'Incomplete'
     *
     * @returns {Array} list of ids
     */
    function getIncompleteActivityStatusIDs () {
      return _.filter(ActivityStatus.getAll(), function (status) {
        return status.filter === INCOMPLETE_ACTIVITY_STATUS_CATEGORY;
      }).map(function (status) {
        return status.value;
      });
    }
  }
})(angular, CRM._);
