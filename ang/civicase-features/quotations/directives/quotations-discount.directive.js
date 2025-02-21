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
    $scope.relevantProducts = false;
    const ctrl = angular.extend(this, $scope.model, searchTaskBaseTrait);
    ctrl.stage = 'form';
    ctrl.products = '';
    $scope.submitInProgress = false;

    this.applyDiscount = () => {
      $q(async function (resolve, reject) {
        const updatedSalesOrder = {};

        for (const salesOrderId of ctrl.ids) {
          const result = await CaseUtils.getSalesOrderAndLineItems(salesOrderId);
          let selectedProducts = [];
          if (ctrl.products.length > 0) {
            selectedProducts = Array.isArray(ctrl.products) ? ctrl.products : ctrl.products.split(',');
          } else if ($scope.relevantProducts) {
            selectedProducts = $scope.relevantProducts;
          }
          result.items.forEach((lineItem, index) => {
            // The line item is part of the product user desires to apply discount to
            if (lineItem.product_id && selectedProducts.some(product =>
              String(product) === String(lineItem.product_id)
            )) {
              const newDiscount = result.items[index].discounted_percentage + ctrl.discount;
              result.items[index].discounted_percentage = Math.min(100, newDiscount);
              updatedSalesOrder[salesOrderId] = result;
            }
          });
        }

        if (Object.keys(updatedSalesOrder).length > 0) {
          await crmApi4('CaseSalesOrder', 'save', { records: Object.values(updatedSalesOrder) });
        }

        ctrl.close();
        CRM.alert(`Discount applied to ${Object.values(updatedSalesOrder).length} Quotation(s) successfully`, ts('Success'), 'success');
      });
    };

    $q(async function () {
      CRM.$.blockUI();
      const productIds = new Set();

      for (const salesOrderId of ctrl.ids) {
        const result = await CaseUtils.getSalesOrderAndLineItems(salesOrderId);
        result.items.forEach((lineItem) => {
          if (lineItem.product_id) {
            productIds.add(lineItem.product_id);
          }
        });
      }

      $scope.$apply(() => {
        $scope.relevantProducts = false;
        $scope.relevantProducts = [...productIds];
      });
      CRM.$.unblockUI();
    });
  }
})(angular, CRM._);
