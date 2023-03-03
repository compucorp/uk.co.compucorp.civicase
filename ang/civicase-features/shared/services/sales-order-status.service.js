(function (angular, $, _, CRM) {
  var module = angular.module('civicase-features');

  module.service('SalesOrderStatus', SalesOrderStatus);

  /**
   * SalesOrderStatus Service
   */
  function SalesOrderStatus () {
    this.getAll = function () {
      return CRM['civicase-features'].salesOrderStatus;
    };

    this.getValueByName = function (name) {
      return CRM['civicase-features']
        .salesOrderStatus
        .filter(status => status.name === name)
        .pop().value || '';
    };
  }
})(angular, CRM.$, CRM._, CRM);
