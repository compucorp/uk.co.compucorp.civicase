(function (angular) {
  const module = angular.module('civicase-features');

  module.config(function ($windowProvider, tsProvider, CaseDetailsTabsProvider) {
    const $window = $windowProvider.$get();
    const ts = tsProvider.$get();
    const featuresTab = [
      {
        name: 'Quotations',
        label: ts('Quotations'),
        weight: 100
      }, {
        name: 'Invoices',
        label: ts('Invoices'),
        weight: 110
      }
    ];

    featuresTab.forEach(feature => {
      if (caseTypeCategoryHasFeatureEnabled(feature.name)) {
        CaseDetailsTabsProvider.addTabs([feature]);
      }
    });

    /**
     * Returns the current case type category parameter. This is used instead of
     * the $location service because the later is not available at configuration
     * time.
     *
     * @returns {string|null} the name of the case type category, or null.
     */
    function getCaseTypeCategory () {
      const urlParamRegExp = /case_type_category=([^&]+)/i;
      const currentSearch = decodeURIComponent($window.location.search);
      const results = urlParamRegExp.exec(currentSearch);

      return results && results[1];
    }

    /**
     * Returns true if the current case type category has quotations
     * features enabled
     *
     * @param {string} feature THe name of the feature
     *
     * @returns {boolean} true if quotations is enabled, otherwise false
     */
    function caseTypeCategoryHasFeatureEnabled (feature) {
      const caseTypeCategory = parseInt(getCaseTypeCategory());
      const quotationCaseTypeCategories = CRM['civicase-features'].featureCaseTypes[feature.toLocaleLowerCase()] || [];
      if (Array.isArray(quotationCaseTypeCategories) && caseTypeCategory) {
        return quotationCaseTypeCategories.includes(caseTypeCategory);
      }

      return false;
    }
  });
})(angular);
