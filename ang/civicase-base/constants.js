(function (angular, configuration) {
  var module = angular.module('civicase-base');

  module
    .constant('allowCaseLocks', configuration.allowCaseLocks)
    .constant('allowLinkedCasesPage', configuration.allowLinkedCasesPage)
    .constant('allowMultipleCaseClients', configuration.allowMultipleCaseClients)
    .constant('currentCaseCategory', configuration.currentCaseCategory);
})(angular, CRM['civicase-base']);
