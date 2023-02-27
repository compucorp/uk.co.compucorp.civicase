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
   * @param {object} $window window object of the browser
   */
  function quotationsListController ($scope, $window) {
    $scope.redirectToQuotationCreationScreen = redirectToQuotationCreationScreen;

    /**
     * Redirect user to new quotation screen
     */
    function redirectToQuotationCreationScreen () {
      $window.location.href = '/civicrm/case-features/a#/new';
    }
  }
})(angular, CRM._);
