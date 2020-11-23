(function (angular, _, crmTs) {
  var module = angular.module('civicase-base');

  module.service('CivicaseUtil', function () {
    this.capitalizeFirstLetterAndRemoveUnderScore = capitalizeFirstLetterAndRemoveUnderScore;

    /**
     * @param {string} string string to capitalize
     * @returns {string} capitalized string
     */
    function capitalizeFirstLetterAndRemoveUnderScore (string) {
      return (string.charAt(0).toUpperCase() + string.slice(1)).replace('_', '');
    }
  });
})(angular, CRM._, window.ts);
