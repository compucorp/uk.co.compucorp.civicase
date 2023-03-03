(function (angular, $, _) {
  var module = angular.module('civicase-features');

  module.directive('quotationsCreate', function () {
    return {
      restrict: 'E',
      controller: 'quotationsCreateController',
      templateUrl: '~/civicase-features/quotations/directives/quotations-create.directive.html',
      scope: {}
    };
  });

  module.controller('quotationsCreateController', quotationsCreateController);

  /**
   * @param {object} $scope the controller scope
   * @param {object} $window window object of the browser
   * @param {object} CurrencyCodes CurrencyCodes service
   * @param {Function} civicaseCrmApi crm api service
   * @param {object} Contact contact service
   * @param {object} crmApi4 api V4 service
   * @param {object} FeatureCaseTypes FeatureCaseTypes service
   * @param {object} SalesOrderStatus SalesOrderStatus service
   * @param {object} CaseUtils case utility service
   */
  function quotationsCreateController ($scope, $window, CurrencyCodes, civicaseCrmApi, Contact, crmApi4, FeatureCaseTypes, SalesOrderStatus, CaseUtils) {
    const defaultCurrency = 'GBP';
    const productsCache = new Map();
    const financialTypesCache = new Map();

    $scope.formValid = true;
    $scope.roundTo = roundTo;
    $scope.submitInProgress = false;
    $scope.caseApiParam = caseApiParam;
    $scope.saveQuotation = saveQuotation;
    $scope.calculateSubtotal = calculateSubtotal;
    $scope.currencyCodes = CurrencyCodes.getAll();
    $scope.handleProductChange = handleProductChange;
    $scope.handleCurrencyChange = handleCurrencyChange;
    $scope.salesOrderStatus = SalesOrderStatus.getAll();
    $scope.handleFinancialTypeChange = handleFinancialTypeChange;
    $scope.currencySymbol = CurrencyCodes.getSymbol(defaultCurrency);

    (function init () {
      initializeSalesOrder();
      $scope.newSalesOrderItem = newSalesOrderItem;
      CRM.wysiwyg.create('#sales-order-description');
      $scope.removeSalesOrderItem = removeSalesOrderItem;

      $scope.$on('totalChange', _.debounce(handleTotalChange, 250));
    }());

    /**
     * Initializess the sales order object
     */
    function initializeSalesOrder () {
      $scope.salesOrder = {
        currency: defaultCurrency,
        status_id: SalesOrderStatus.getValueByName('new'),
        owner_id: Contact.getCurrentContactID(),
        quotation_date: $.datepicker.formatDate('yy-mm-dd', new Date()),
        items: [{
          product_id: null,
          item_description: null,
          financial_type_id: null,
          unit_price: null,
          quantity: null,
          discounted_percentage: null,
          tax_rate: 0,
          subtotal_amount: 0
        }],
        total: 0,
        grandTotal: 0
      };
      $scope.total = 0;
      $scope.taxRates = [];
    }

    /**
     * Removes a sales order line item
     *
     * @param {number} index element index to be removed
     */
    function removeSalesOrderItem (index) {
      $scope.salesOrder.items.splice(index, 1);
    }

    /**
     * Initializes empty sales order line item
     */
    function newSalesOrderItem () {
      $scope.salesOrder.items.push({
        product: null,
        description: null,
        financial_type_id: null,
        unit_price: null,
        quantity: null,
        discounted_percentage: null,
        tax_rate: 0,
        subtotal_amount: 0
      });
    }

    /**
     * Persists quotaiton and redirects on success
     */
    function saveQuotation () {
      if (!validateForm()) {
        return;
      }

      $scope.submitInProgress = true;
      crmApi4('CaseSalesOrder', 'save', { records: [$scope.salesOrder] })
        .then(function (results) {
          $scope.submitInProgress = false;
          showSucessNotification();
          redirectToAppropraitePage();
        }, function (failure) {
          $scope.submitInProgress = false;
          CRM.alert('Unable to generate quotations', ts('Error'), 'error');
        });
    }

    /**
     * Validates form before saving
     *
     * @returns {boolean} true if form is valid, otherwise false
     */
    function validateForm () {
      angular.forEach($scope.quotationsForm.$$controls, function (control) {
        control.$setDirty();
        control.$validate();
      });

      return $scope.quotationsForm.$valid;
    }

    /**
     * Updates description and unit price if user selects a product
     *
     * @param {*} index index of the sales order line item
     */
    function handleProductChange (index) {
      if (!$scope.salesOrder.items[index].product_id) {
        return;
      }
      const updateProductDependentFields = (productId) => {
        $scope.salesOrder.items[index].item_description = productsCache.get(productId).description;
        $scope.salesOrder.items[index].unit_price = parseFloat(productsCache.get(productId).price);
        calculateSubtotal(index);
      };

      const productId = $scope.salesOrder.items[index].product_id;
      if (productsCache.has(productId)) {
        updateProductDependentFields(productId);
        return;
      }

      civicaseCrmApi('Product', 'get', { id: productId })
        .then(function (result) {
          if (result.count > 0) {
            productsCache.set(productId, result.values[productId]);
            updateProductDependentFields(productId);
          }
        });
    }

    /**
     * Update currency symbol if currecny field is upddated
     */
    function handleCurrencyChange () {
      $scope.currencySymbol = CurrencyCodes.getSymbol($scope.salesOrder.currency);
    }

    /**
     * Update tax filed and regenrate line item tax rates for line itme financial types
     *
     * @param {number} index index of the sales order line item
     */
    function handleFinancialTypeChange (index) {
      $scope.salesOrder.items[index].tax_rate = 0;
      $scope.$emit('totalChange');

      const updateFinancialTypeDependentFields = (financialTypeId) => {
        $scope.salesOrder.items[index].tax_rate = financialTypesCache.get(financialTypeId).tax_rate;
        $scope.$emit('totalChange');
      };

      const financialTypeId = $scope.salesOrder.items[index].financial_type_id;
      if (financialTypeId && financialTypesCache.has(financialTypeId)) {
        updateFinancialTypeDependentFields(financialTypeId);
        return;
      }

      if (financialTypeId) {
        civicaseCrmApi('EntityFinancialAccount', 'get', {
          account_relationship: 'Sales Tax Account is',
          entity_table: 'civicrm_financial_type',
          entity_id: financialTypeId,
          'api.FinancialAccount.get': { id: '$value.financial_account_id' }
        })
          .then(function (result) {
            if (result.count > 0) {
              financialTypesCache.set(financialTypeId, Object.values(result.values)[0]['api.FinancialAccount.get'].values[0]);
              updateFinancialTypeDependentFields(financialTypeId);
            }
          });
      }
    }

    /**
     * Sums sales order line item without tax, and computes tax rates separately
     *
     * @param {number} index index of the sales order line item
     */
    function calculateSubtotal (index) {
      const item = $scope.salesOrder.items[index];
      if (!item) {
        return;
      }

      item.subtotal_amount = item.unit_price * item.quantity * ((100 - item.discounted_percentage) / 100) || 0;
      $scope.$emit('totalChange');
    }

    /**
     * Rounds floating ponumber n to specified number of places
     *
     * @param {*} n number to round
     * @param {*} place decimal places to round to
     * @returns {number} the rounded off number
     */
    function roundTo (n, place) {
      return +(Math.round(n + 'e+' + place) + 'e-' + place);
    }

    /**
     * Show Quotation success create notification
     */
    function showSucessNotification () {
      CRM.alert('Your Quotation has been generated successfully.', ts('Saved'), 'success');
    }

    /**
     * Handles page rediection after successfully creating quotation.
     *
     * redirects to main quotation list page if no case is selected
     * else redirects to the case view of the selected case.
     */
    function redirectToAppropraitePage () {
      if (!$scope.salesOrder.case_id) {
        $window.location.href = 'a#/quotations';
      }

      CaseUtils.getDashboardLink($scope.salesOrder.case_id).then(link => {
        $window.location.href = link;
      });
    }

    /**
     * @returns {object} api parameters for Case.getlist
     */
    function caseApiParam () {
      const caseTypeCategoryId = FeatureCaseTypes.getCaseTypes('quotations');
      return { params: { 'case_id.case_type_id.case_type_category': { IN: caseTypeCategoryId } } };
    }

    /**
     * Computes total and tax rates from API
     */
    function handleTotalChange () {
      crmApi4('CaseSalesOrder', 'computeTotal', {
        lineItems: $scope.salesOrder.items
      }).then(function (results) {
        $scope.taxRates = results[0].taxRates;
        $scope.salesOrder.total = results[0].totalBeforeTax;
        $scope.salesOrder.grandTotal = results[0].totalAfterTax;
      }, function (failure) {
        // handle failure
      });
    }
  }
})(angular, CRM.$, CRM._);
