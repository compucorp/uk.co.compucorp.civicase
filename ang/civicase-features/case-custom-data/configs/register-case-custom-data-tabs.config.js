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
        customGroup: group,
        isVisible: function (caseItem) {
          return isGroupForCaseType(group, caseItem && caseItem.case_type_id);
        }
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

  /**
   * Whether a custom group applies to cases of a case type.
   *
   * `case_type_ids` lists the case types the group is limited to, or is null
   * when it applies to all of them. See TCOSB-104.
   *
   * @param {object} group the custom group ({case_type_ids, ...})
   * @param {string|number} caseTypeId the case's case type ID
   * @returns {boolean} true if the group's tab should show on the case
   */
  function isGroupForCaseType (group, caseTypeId) {
    if (!Array.isArray(group.case_type_ids)) {
      return true;
    }

    return group.case_type_ids.map(String).indexOf(String(caseTypeId)) !== -1;
  }
})(angular, CRM);
