(function (angular, _) {
  var module = angular.module('case-features');

  module.directive('quotationsList', function () {
    return {
      restrict: 'E',
      controller: 'quotationsListController',
      templateUrl: '~/case-features/quotations/directives/quotations-list.directive.html',
      scope: {}
    };
  });

  module.controller('quotationsListController', quotationsListController);

  /**
   * @param {object} $scope the controller scope
   */
  function quotationsListController ($scope, $rootScope) {
    
  }
})(angular, CRM._);
