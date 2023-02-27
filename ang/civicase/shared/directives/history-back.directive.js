(function (angular, $window) {
  var module = angular.module('civicase');

  module.directive('historyBack', function () {
    return {
      restrict: 'A',
      link: function (scope, elem, attrs) {
        elem.bind('click', function () {
          $window.history.back();
        });
      }
    };
  });
})(angular, window);
