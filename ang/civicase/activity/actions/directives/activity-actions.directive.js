(function ($, _, angular) {
  var module = angular.module('civicase');

  module.directive('civicaseActivityActions', function () {
    return {
      scope: {
        mode: '@',
        selectedActivities: '=',
        isSelectAll: '=',
        totalCount: '=',
        params: '='
      },
      require: '?^civicaseCaseDetails',
      controller: civicaseActivityActionsController,
      templateUrl: '~/civicase/activity/actions/directives/activity-actions.directive.html',
      restrict: 'A',
      link: civicaseActivityActionsLink
    };

    /**
     * Angular JS's link function for the directive civicaseActivityActions
     *
     * @param {object} $scope angular scope
     * @param {object} attrs attributes
     * @param {object} element element
     * @param {object} caseDetails case details service
     */
    function civicaseActivityActionsLink ($scope, attrs, element, caseDetails) {
      if (caseDetails) {
        // TODO - Unit test pending
        $scope.isCaseSummaryPage = true;
        $scope.getEditActivityUrl = caseDetails.getEditActivityUrl;
        $scope.getPrintActivityUrl = caseDetails.getPrintActivityUrl;
      }
    }
  });

  module.controller('civicaseActivityActionsController', civicaseActivityActionsController);

  /**
   * @param {object} $window window object
   * @param {object} $rootScope rootscope
   * @param {object} $scope scope
   * @param {object} crmApi crm api
   * @param {object} getActivityFeedUrl service to get activity feed url
   * @param {object} MoveCopyActivityAction move copy action service
   * @param {object} TagsActivityAction tags action service
   * @param {object} DeleteActivityAction delete activity service
   * @param {object} ts ts
   * @param {object} ActivityType ActivityType service
   */
  function civicaseActivityActionsController ($window, $rootScope, $scope, crmApi, getActivityFeedUrl, MoveCopyActivityAction, TagsActivityAction, DeleteActivityAction, ts, ActivityType) {
    $scope.ts = ts;
    $scope.getActivityFeedUrl = getActivityFeedUrl;
    $scope.deleteActivity = DeleteActivityAction.deleteActivity;
    $scope.moveCopyActivity = MoveCopyActivityAction.moveCopyActivities;
    $scope.manageTags = TagsActivityAction.manageTags;
    $scope.isActivityEditable = isActivityEditable;
    $scope.printReport = printReport;

    /**
     * Print a report for the sent activities
     *
     * @param {Array} selectedActivities selected activities
     */
    function printReport (selectedActivities) {
      var url = $scope.getPrintActivityUrl(selectedActivities);

      $window.open(url, '_blank').focus();
    }

    /**
     * Checks if the sent activity is enabled
     *
     * @param {object} activity activity
     * @returns {boolean} if activity is editable
     */
    function isActivityEditable (activity) {
      var activityType = ActivityType.getAll()[activity.activity_type_id].name;
      var nonEditableActivityTypes = [
        'Email',
        'Print PDF Letter'
      ];

      return !_.includes(nonEditableActivityTypes, activityType) && $scope.getEditActivityUrl;
    }
  }
})(CRM.$, CRM._, angular);
