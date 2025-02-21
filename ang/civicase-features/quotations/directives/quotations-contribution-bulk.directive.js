(function (angular, $, _) {
  var module = angular.module('civicase-features');

  module.directive('quotationContributionBulk', function () {
    return {
      restrict: 'E',
      controller: 'quotationContributionBulkController',
      templateUrl: '~/civicase-features/quotations/directives/quotations-contribution-bulk.directive.html',
      scope: {}
    };
  });

  module.controller('quotationContributionBulkController', quotationContributionBulkController);

  /**
   * @param {object} $q ng-promise object
   * @param {object} $scope the controller scope
   * @param {object} crmApi4 api V4 service
   * @param {object} searchTaskBaseTrait searchkit trait
   * @param {object} CaseUtils case utility service
   * @param {object} SalesOrderStatus SalesOrderStatus service
   */
  function quotationContributionBulkController ($q, $scope, crmApi4, searchTaskBaseTrait, CaseUtils, SalesOrderStatus) {
    $scope.ts = CRM.ts('civicase');
    $scope.relevantProducts = false;

    const ctrl = angular.extend(this, $scope.model, searchTaskBaseTrait);
    ctrl.stage = 'form';
    $scope.submitInProgress = false;
    ctrl.data = {
      toBeInvoiced: 'percent',
      percentValue: null,
      statusId: null,
      financialTypeId: null,
      products: [],
      date: $.datepicker.formatDate('yy-mm-dd', new Date())
    };
    ctrl.salesOrderStatus = SalesOrderStatus.getAll();
    this.progress = null;
    const BATCH_SIZE = 50;

    (function () {
      CaseUtils.getSalesOrderAndLineItems(ctrl.ids[0]).then((result) => {
        ctrl.data.financialTypeId = result.items[0].financial_type_id ?? null;
        ctrl.data.statusId = Number(result.status_id).toString();
      });
    })();

    this.createBulkContribution = () => {
      $q(async function (resolve, reject) {
        ctrl.run = true;
        ctrl.progress = 0;
        let contributionCreated = 0;
        let index = 0;
        const chunkedIds = _.chunk(ctrl.ids, BATCH_SIZE);
        for (const salesOrderIds of chunkedIds) {
          try {
            if (ctrl.data.products.length > 0) {
              ctrl.data.products = ctrl.data.products.split(',');
            } else {
              ctrl.data.products = [];
            }
            const result = await crmApi4('CaseSalesOrder', 'contributionCreateAction', { ...ctrl.data, salesOrderIds });
            contributionCreated += result.created_contributions_count ?? 0;
          } catch (error) {
            console.log(error);
          } finally {
            index += BATCH_SIZE;
            ctrl.progress = (index * 100) / ctrl.ids.length;
          }
        }

        ctrl.run = false;
        ctrl.close();
        const contributionNotCreated = ctrl.ids.length - contributionCreated;
        let message = `${contributionCreated} contributions have been generated`;
        message += contributionNotCreated > 0 ? ` and no contributions were created for ${contributionNotCreated} quotes as there was no remaining amount to be invoiced` : '';
        CRM.alert(message, ts('Success'), 'success');
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
})(angular, CRM.$, CRM._);
