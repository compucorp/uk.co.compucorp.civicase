/* eslint no-param-reassign: "error" */

(function () {
  var module = angular.module('civicase.data');

  CRM['civicase-base'].caseTypeCategories = {
    1: { value: '1', label: 'Cases', name: 'Cases' },
    2: { value: '2', label: 'Prospecting', name: 'Prospecting' },
    3: { value: '3', label: 'Awards', name: 'awards' }
  };

  module.constant('caseTypeCategoriesMockData', CRM['civicase-base'].caseTypeCategories);
}(CRM));
