(function (angular, _) {
  var module = angular.module('civicase-features');

  module.directive('quotationsList', function () {
    return {
      restrict: 'E',
      controller: 'quotationsListController',
      templateUrl: '~/civicase-features/quotations/directives/quotations-list.directive.html',
      scope: {}
    };
  });

  module.controller('quotationsListController', quotationsListController);

  /**
   * @param {object} $scope the controller scope
   * @param {object} $location the location service
   * @param {object} $window window object of the browser
   */
  function quotationsListController ($scope, $location, $window) {
    $scope.redirectToQuotationCreationScreen = redirectToQuotationCreationScreen;

    /**
     * Redirect user to new quotation screen
     */
    function redirectToQuotationCreationScreen () {
      let url = '/civicrm/case-features/a#/new';
      const caseId = $location.search().caseId;
      if (caseId) {
        url += `?caseId=${caseId}`;
      }

      $window.location.href = url;
    }
  }
})(angular, CRM._);
