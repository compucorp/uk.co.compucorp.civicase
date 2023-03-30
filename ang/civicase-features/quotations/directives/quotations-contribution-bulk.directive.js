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

    this.createBulkContribution = () => {
      $q(async function (resolve, reject) {
        ctrl.run = true;
        let contributionCreated = 0;

        for (const id of ctrl.ids) {
          try {
            await crmApi4('CaseSalesOrder', 'contributionCreateAction', { ...ctrl.data, id });
            contributionCreated++;
          } catch (error) {
            console.log(error);
          }
        }

        ctrl.run = false;
        ctrl.close();
        CRM.alert(`${contributionCreated} Invoices have been generated.`, ts('Success'), 'success');
      });
    };
  }
})(angular, CRM.$, CRM._);
