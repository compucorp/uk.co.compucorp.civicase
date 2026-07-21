(function (angular, CRM) {
  var module = angular.module('civicase-features');

  // Registers one case-detail tab per repeatable ("Tab with table") Case custom
  // group, plus a matching {tabName}CaseTab service (the CaseDetailsTabs provider
  // resolves content via that service). Groups come from the civicase-features
  // settings (see CRM_Civicase_Settings::setRepeatableCaseCustomGroups).
  module.config(function ($provide, CaseDetailsTabsProvider) {
    var groups = ((CRM['civicase-features'] || {}).repeatableCaseCustomGroups) || [];

    groups.forEach(function (group, index) {
      var tabName = 'CaseCustomData_' + group.name;

      CaseDetailsTabsProvider.addTabs([{
        name: tabName,
        label: group.title,
        weight: 200 + index,
        customGroup: group
      }]);

      // Each tab needs a "<name>CaseTab" service returning the content template.
      // All groups share one template; the content resolves its own group.
      $provide.factory(tabName + 'CaseTab', function () {
        return {
          activeTabContentUrl: function () {
            return '~/civicase-features/case-custom-data/directives/case-custom-data-tab-content.html';
          }
        };
      });
    });
  });
})(angular, CRM);
