(function ($, _, angular) {
  var module = angular.module('workflow');

  module.directive('workflowList', function () {
    return {
      scope: {
        caseTypeCategory: '@'
      },
      controller: 'workflowListController',
      templateUrl: '~/workflow/directives/workflow-list.directive.html',
      restrict: 'E'
    };
  });

  module.controller('workflowListController', workflowListController);

  /**
   * @param {object} $scope scope object
   * @param {object} $injector injector service of angular
   * @param {object} ts ts
   * @param {object} civicaseCrmApi service to use civicrm api
   * @param {object[]} WorkflowListColumns list of workflow list columns
   * @param {object[]} WorkflowListActionItems list of workflow list action items
   * @param {object} CaseTypeCategory case type catgory service
   * @param {object} CivicaseUtil utility service
   */
  function workflowListController ($scope, $injector, ts, civicaseCrmApi,
    WorkflowListColumns, WorkflowListActionItems, CaseTypeCategory,
    CivicaseUtil) {
    $scope.ts = ts;
    $scope.isLoading = false;
    $scope.workflows = [];
    $scope.actionItems = WorkflowListActionItems;
    $scope.tableColumns = _.map(WorkflowListColumns, function (column) {
      column = _.extend({}, column);
      column.isVisible =
        !column.onlyVisibleForInstance ||
        CaseTypeCategory.isInstance(
          $scope.caseTypeCategory,
          column.onlyVisibleForInstance
        );

      return column;
    });

    (function init () {
      refreshWorkflowsList();

      $scope.$on('workflow::list::refresh', refreshWorkflowsList);
    }());

    /**
     * Refresh workflows list
     */
    function refreshWorkflowsList () {
      $scope.isLoading = true;

      getWorkflows($scope.caseTypeCategory)
        .then(function (workflows) {
          $scope.workflows = workflows;
        })
        .finally(function () {
          $scope.isLoading = false;
        });
    }

    /**
     * Get list of workflows for the sent case type category
     *
     * @param {string} caseTypeCategory case type category
     * @returns {Promise} list of workflows
     */
    function getWorkflows (caseTypeCategory) {
      var categoryObject = CaseTypeCategory.findByName(caseTypeCategory);
      var instanceName = CaseTypeCategory.getCaseTypeCategoryInstance(categoryObject.value).name;

      return getServiceForInstance(instanceName)
        .getWorkflowsList(caseTypeCategory);
    }

    /**
     * Searches for a angularJS service for the current case type category
     * instance, if not found, returns the service for case management service
     * as default.
     *
     * @param {string} instanceName name of the instance
     * @returns {object/null} service
     */
    function getServiceForInstance (instanceName) {
      var CASE_MANAGEMENT_INSTACE_NAME = 'case_management';

      try {
        return $injector.get(
          CivicaseUtil.capitalizeFirstLetterAndRemoveUnderScore(instanceName) + 'Workflow'
        );
      } catch (e) {
        return $injector.get(
          CivicaseUtil.capitalizeFirstLetterAndRemoveUnderScore(CASE_MANAGEMENT_INSTACE_NAME) + 'Workflow'
        );
      }
    }
  }
})(CRM.$, CRM._, angular);
