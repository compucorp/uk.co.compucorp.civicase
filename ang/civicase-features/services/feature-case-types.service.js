(function (angular, $, _, CRM) {
  var module = angular.module('civicase-features');

  module.service('FeatureCaseTypes', FeatureCaseTypes);

  /**
   * FeatureCaseTypes Service
   */
  function FeatureCaseTypes () {
    this.getCaseTypes = function ($feature) {
      return CRM['civicase-features'].featureCaseTypes[$feature] || [];
    };
  }
})(angular, CRM.$, CRM._, CRM);
