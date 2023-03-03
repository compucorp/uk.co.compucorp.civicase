(function (angular, $, _, CRM) {
  var module = angular.module('civicase-features');

  module.service('CaseUtils', CaseUtils);

  /**
   * CaseUtils Service
   *
   * @param {object} $q ng promise object
   * @param {Function} civicaseCrmApi civicrm api service
   */
  function CaseUtils ($q, civicaseCrmApi) {
    this.getDashboardLink = function (id) {
      return $q(function (resolve, reject) {
        const params = { id, return: ['case_type_category', 'case_type_id'] };
        civicaseCrmApi('Case', 'getdetails', params)
          .then(function (result) {
            const categoryId = result.values[id].case_type_category;
            const link = CRM.url(`/case/a/?case_type_category=${categoryId}#/case/list?cf={"case_type_category":"${categoryId}"}&caseId=${id}`);

            resolve(link);
          });
      });
    };
  }
})(angular, CRM.$, CRM._, CRM);
