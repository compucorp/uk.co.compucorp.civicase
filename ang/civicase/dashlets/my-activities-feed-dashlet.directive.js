(function (_, angular) {
  var module = angular.module('civicase');

  module.directive('civicaseMyActivitiesFeedDashlet', function () {
    return {
      controller: 'civicaseMyActivitiesFeedDashletController',
      templateUrl: '~/civicase/dashlets/my-activities-feed-dashlet.directive.html'
    };
  });

  module.controller('civicaseMyActivitiesFeedDashletController', civicaseMyActivitiesFeedDashletController);

  /**
   * My Activities Feed Dashlet controller.
   *
   * @param {object} $scope Scope Object reference.
   * @param {object} ActivityStatus Activity Status service.
   * @param {object} Contact Contact service.
   */
  function civicaseMyActivitiesFeedDashletController ($scope, ActivityStatus, Contact) {
    (function init () {
      var INCOMPLETE_ACTIVITY_STATUS_CATEGORY = '0';
      var incompletedActivityStatusIds = _.chain(ActivityStatus.getAll())
        .filter({ filter: INCOMPLETE_ACTIVITY_STATUS_CATEGORY })
        .map('value')
        .value();

      $scope.filters = {
        $contact_id: Contact.getCurrentContactID(),
        '@involvingContact': 'myActivities',
        status_id: incompletedActivityStatusIds
      };
    })();
  }
})(CRM._, angular);
