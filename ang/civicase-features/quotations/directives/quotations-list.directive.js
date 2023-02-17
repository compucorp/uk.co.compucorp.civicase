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
   */
  function quotationsListController ($scope) {

  }
})(angular, CRM._);
