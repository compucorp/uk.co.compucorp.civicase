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

    const ctrl = angular.extend(this, $scope.model, searchTaskBaseTrait);
    ctrl.stage = 'form';
    $scope.submitInProgress = false;
    ctrl.data = {
      toBeInvoiced: 'percent',
      percentValue: 0,
      statusId: null,
      financialTypeId: null,
      date: $.datepicker.formatDate('yy-mm-dd', new Date())
    };
    ctrl.salesOrderStatus = SalesOrderStatus.getAll();
    this.progress = null;
    const BATCH_SIZE = 50;

    this.createBulkContribution = () => {
      $q(async function (resolve, reject) {
        ctrl.run = true;
        ctrl.progress = 0;
        let contributionCreated = 0;
        let index = 0;
        const chunkedIds = _.chunk(ctrl.ids, BATCH_SIZE);
        for (const ids of chunkedIds) {
          try {
            await crmApi4('CaseSalesOrder', 'contributionCreateAction', { ...ctrl.data, ids });
            contributionCreated += ids.length;
          } catch (error) {
            console.log(error);
          } finally {
            index += BATCH_SIZE;
            ctrl.progress = (index * 100) / ctrl.ids.length;
          }
        }

        ctrl.run = false;
        ctrl.close();
        CRM.alert(`${contributionCreated} Invoices have been generated.`, ts('Success'), 'success');
      });
    };
  }
})(angular, CRM.$, CRM._);
