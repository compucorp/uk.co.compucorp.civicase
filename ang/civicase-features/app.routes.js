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
    $routeProvider.when('/quotations/new', {
      template: function () {
        var urlParams = UrlParametersProvider.parse(window.location.search);
        return `
          <quotations-create case-type-category=${urlParams.case_type_category}"> </quotations-create>
        `;
      }
    });
  });
})(angular, CRM.$, CRM._);
