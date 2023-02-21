(function (angular, $, _) {
  var module = angular.module('civicase-features');

  module.config(function ($routeProvider, UrlParametersProvider) {
    $routeProvider.when('/quotations', {
      template: function () {
        var urlParams = UrlParametersProvider.parse(window.location.search);
        return `
          <quotations-list case-type-category= ${urlParams.case_type_category}"> </quotations-list>
        `;
      }
    });
  });
})(angular, CRM.$, CRM._);
