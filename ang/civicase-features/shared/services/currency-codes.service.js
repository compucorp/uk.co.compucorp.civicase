(function (angular, $, _, CRM) {
  var module = angular.module('civicase-features');

  module.service('CurrencyCodes', CurrencyCodes);

  /**
   * CurrencyCodes Service
   */
  function CurrencyCodes () {
    this.getAll = function () {
      return CRM['civicase-features'].currencyCodes;
    };

    this.getSymbol = function (name) {
      return CRM['civicase-features']
        .currencyCodes
        .filter(currency => currency.name === name)
        .pop().symbol || '£';
    };
  }
})(angular, CRM.$, CRM._, CRM);
