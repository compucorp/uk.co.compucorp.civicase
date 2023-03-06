(function (angular) {
  const module = angular.module('civicase-features');
  const FEATURE_NAME = 'quotations';

  module.config(function ($windowProvider, tsProvider, CaseDetailsTabsProvider) {
    var $window = $windowProvider.$get();
    var ts = tsProvider.$get();
    var quotationsTab = {
      name: 'Quotations',
      label: ts('Quotations'),
      weight: 100
    };

    if (caseTypeCategoryHasQuotationEnabled()) {
      CaseDetailsTabsProvider.addTabs([
        quotationsTab
      ]);
    }

    /**
     * Returns the current case type category parameter. This is used instead of
     * the $location service because the later is not available at configuration
     * time.
     *
     * @returns {string|null} the name of the case type category, or null.
     */
    function getCaseTypeCategory () {
      var urlParamRegExp = /case_type_category=([^&]+)/i;
      var currentSearch = decodeURIComponent($window.location.search);
      var results = urlParamRegExp.exec(currentSearch);

      return results && results[1];
    }

    /**
     * Returns true if the current case type category has quotations
     * features enabled
     *
     * @returns {boolean} true if quotations is enabled, otherwise false
     */
    function caseTypeCategoryHasQuotationEnabled () {
      const caseTypeCategory = parseInt(getCaseTypeCategory());
      const quotationCaseTypeCategories = CRM['civicase-features'].featureCaseTypes[FEATURE_NAME] || [];
      if (Array.isArray(quotationCaseTypeCategories) && caseTypeCategory) {
        return quotationCaseTypeCategories.includes(caseTypeCategory);
      }

      return false;
    }
  });
})(angular);
