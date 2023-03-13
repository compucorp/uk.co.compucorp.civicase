(function (angular, _) {
  var module = angular.module('civicase-features');

  module.directive('quotationsDiscount', function () {
    return {
      restrict: 'E',
      controller: 'quotationsDiscountController',
      templateUrl: '~/civicase-features/quotations/directives/quotations-discount.directive.html',
      scope: {}
    };
  });

  module.controller('quotationsDiscountController', quotationsDiscountController);

  /**
   * @param {object} $q ng-promise object
   * @param {object} $scope the controller scope
   * @param {object} crmApi4 api V4 service
   * @param {object} searchTaskBaseTrait searchkit trait
   * @param {object} CaseUtils case utility service
   */
  function quotationsDiscountController ($q, $scope, crmApi4, searchTaskBaseTrait, CaseUtils) {
    $scope.ts = CRM.ts('civicase');
    const ctrl = angular.extend(this, $scope.model, searchTaskBaseTrait);
    ctrl.stage = 'form';
    $scope.submitInProgress = false;

    this.applyDiscount = () => {
      $q(async function (resolve, reject) {
        const updatedSalesOrder = {};

        for (const salesOrderId of ctrl.ids) {
          const result = await CaseUtils.getSalesOrderAndLineItems(salesOrderId);
          const selectedProducts = ctrl.products.split(',');
          result.items.forEach((lineItem, index) => {
            // The line item is part of the product user desires to apply discount to
            if (lineItem.product_id && selectedProducts.includes((lineItem.product_id).toString())) {
              const newDiscount = result.items[index].discounted_percentage + ctrl.discount;
              result.items[index].discounted_percentage = Math.min(100, newDiscount);
              updatedSalesOrder[salesOrderId] = result;
            }
          });
        }

        await crmApi4('CaseSalesOrder', 'save', { records: Object.values(updatedSalesOrder) });
        ctrl.close();
        CRM.alert(`Discount applied to ${Object.values(updatedSalesOrder).length} Quotation(s) successfully`, ts('Success'), 'success');
      });
    };
  }
})(angular, CRM._);
