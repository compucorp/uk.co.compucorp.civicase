(function (angular, $, _, CRM) {
  var module = angular.module('civicase-features');

  module.service('CaseUtils', CaseUtils);

  /**
   * CaseUtils Service
   *
   * @param {object} $q ng promise object
   * @param {object} crmApi4 api V4 service
   * @param {Function} civicaseCrmApi civicrm api service
   */
  function CaseUtils ($q, crmApi4, civicaseCrmApi) {
    /**
     * Gets the link to a case dashboard
     *
     * @param {number} id ID of the case
     * @returns {Promise}  promise object
     */
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

    /**
     * Retrieves the sales order and its line items from API
     *
     * @param {number} salesOrderId ID of the sales order to retrieve
     * @returns {Promise}  promise object
     */
    this.getSalesOrderAndLineItems = function (salesOrderId) {
      return $q(function (resolve, reject) {
        crmApi4('CaseSalesOrder', 'get', {
          select: ['*', 'case_sales_order_line.*'],
          where: [['id', '=', salesOrderId]],
          limit: 1,
          chain: { items: ['CaseSalesOrderLine', 'get', { where: [['sales_order_id', '=', '$id']], select: ['*', 'product_id.name', 'financial_type_id.name'] }] }
        }).then(async function (caseSalesOrders) {
          if (Array.isArray(caseSalesOrders) && caseSalesOrders.length > 0) {
            const salesOrder = caseSalesOrders.pop();
            resolve(salesOrder);
          }
        });
      });
    };
  }
})(angular, CRM.$, CRM._, CRM);
