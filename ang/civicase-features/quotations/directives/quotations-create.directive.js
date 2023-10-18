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
   * @param {object} $location the location service
   * @param {object} $window window object of the browser
   * @param {object} CurrencyCodes CurrencyCodes service
   * @param {Function} civicaseCrmApi crm api service
   * @param {object} Contact contact service
   * @param {object} crmApi4 api V4 service
   * @param {object} FeatureCaseTypes FeatureCaseTypes service
   * @param {object} SalesOrderStatus SalesOrderStatus service
   * @param {object} CaseUtils case utility service
   * @param {object} crmUiHelp crm ui help service
   */
  function quotationsCreateController ($scope, $location, $window, CurrencyCodes, civicaseCrmApi, Contact, crmApi4, FeatureCaseTypes, SalesOrderStatus, CaseUtils, crmUiHelp) {
    const defaultCurrency = 'GBP';
    const productsCache = new Map();
    const financialTypesCache = new Map();
    $scope.hs = crmUiHelp({ file: 'CRM/Civicase/SalesOrderCtrl' });

    $scope.isUpdate = false;
    $scope.formValid = true;
    $scope.roundTo = roundTo;
    $scope.formatMoney = formatMoney;
    $scope.submitInProgress = false;
    $scope.caseApiParam = caseApiParam;
    $scope.saveQuotation = saveQuotation;
    $scope.calculateSubtotal = calculateSubtotal;
    $scope.currencyCodes = CurrencyCodes.getAll();
    $scope.handleClientChange = handleClientChange;
    $scope.handleProductChange = handleProductChange;
    $scope.membeshipTypesProductDiscountPercentage = 0;
    $scope.handleCurrencyChange = handleCurrencyChange;
    $scope.salesOrderStatus = SalesOrderStatus.getAll();
    $scope.defaultCaseId = $location.search().caseId || null;
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
        clientId: null,
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
        grandTotal: 0,
        case_id: $scope.defaultCaseId
      };
      $scope.total = 0;
      $scope.taxRates = [];

      setDefaultClientID();
      prefillSalesOrderForUpdate();
    }

    /**
     * Pre-fill sales order when updating sales order.
     */
    function prefillSalesOrderForUpdate () {
      const salesOrderId = $location.search().id;

      if (!salesOrderId) {
        $scope.salesOrder.owner_id = Contact.getCurrentContactID();
        return;
      }

      CaseUtils.getSalesOrderAndLineItems(salesOrderId).then((result) => {
        $scope.isUpdate = true;
        $scope.salesOrder = result;
        $scope.salesOrder.quotation_date = $.datepicker.formatDate('yy-mm-dd', new Date(result.quotation_date));
        $scope.salesOrder.status_id = (result.status_id).toString();
        CRM.wysiwyg.setVal('#sales-order-description', $scope.salesOrder.description);
        $scope.$emit('totalChange');
      });
    }

    /**
     * Sets client ID to case client.
     */
    function setDefaultClientID () {
      if (!$scope.defaultCaseId || $scope.isUpdate) {
        return;
      }

      crmApi4('CaseContact', 'get', {
        select: ['contact_id'],
        where: [['case_id', '=', $scope.defaultCaseId]]
      }).then(function (caseContacts) {
        if (Array.isArray(caseContacts) && caseContacts.length > 0) {
          $scope.salesOrder.client_id = caseContacts[0].contact_id ?? null;
          if ($scope.salesOrder.client_id) {
            handleClientChange();
          }
        }
      });
    }

    /**
     * Removes a sales order line item
     *
     * @param {number} index element index to be removed
     */
    function removeSalesOrderItem (index) {
      $scope.salesOrder.items.splice(index, 1);
      $scope.$emit('totalChange');
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
        discounted_percentage: $scope.membeshipTypesProductDiscountPercentage,
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
        $scope.salesOrder.items[index]['product_id.name'] = '';
        return;
      }
      const updateProductDependentFields = (productId) => {
        $scope.salesOrder.items[index].item_description = productsCache.get(productId).description;
        $scope.salesOrder.items[index].unit_price = parseFloat(productsCache.get(productId).price);
        const financialTypeId = productsCache.get(productId).financial_type_id ?? null;
        if (financialTypeId) {
          $scope.salesOrder.items[index].financial_type_id = financialTypeId;
          handleFinancialTypeChange(index);
        }
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
     * Applies any membership type product discounts to
     * each sale order line item if the selected client has any memberships.
     */
    function handleClientChange () {
      const clientID = $scope.salesOrder.client_id;
      crmApi4('Membership', 'get', {
        select: ['membership_type_id.Product_Discounts.Product_Discount_Amount'],
        where: [['contact_id', '=', clientID], ['status_id.is_current_member', '=', true]]
      }).then(function (results) {
        if (!results || results.length < 1) {
          return;
        }
        let discountPercentage = 0;
        results.forEach((membership) => {
          discountPercentage += membership['membership_type_id.Product_Discounts.Product_Discount_Amount'];
        });
        // make sure that the discount percentage cannot be more than 100%
        if (discountPercentage > 100) {
          discountPercentage = 100;
        }
        $scope.membeshipTypesProductDiscountPercentage = discountPercentage;
        applySaleOrderItemPencentageDiscount();
        CRM.alert(ts('Automatic Members Discount Applied'), ts('Product Discount'), 'success');
      });
    }

    /**
     * Applies Membership Type discounted percentage to
     * each sale order item discounted percentage.
     */
    function applySaleOrderItemPencentageDiscount () {
      $scope.salesOrder.items.forEach((item) => {
        item.discounted_percentage = item.discounted_percentage + $scope.membeshipTypesProductDiscountPercentage;
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

      if ($scope.salesOrder.items[index]['financial_type_id.name']) {
        $scope.salesOrder.items[index]['financial_type_id.name'] = '';
      }

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
      validateProductPrice(index);
    }

    /**
     * Ensures the product price doesnt exceed the max price
     *
     * @param {number} index index of the sales order line item
     */
    function validateProductPrice (index) {
      const productId = $scope.salesOrder.items[index].product_id;
      const shouldCompareCost = productId && productsCache.has(productId) && parseFloat(productsCache.get(productId).cost) > 0;
      if (!shouldCompareCost) {
        return;
      }

      const cost = productsCache.get(productId).cost;
      if ($scope.salesOrder.items[index].subtotal_amount > cost) {
        $scope.salesOrder.items[index].quantity = 1;
        $scope.salesOrder.items[index].unit_price = parseFloat(cost);
        CRM.alert('The quotation line item(s) have been set to the maximum premium price', ts('info'), 'info');
        calculateSubtotal(index);
      }
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
      const msg = !$scope.isUpdate ? 'Your Quotation has been generated successfully.' : 'Details updated successfully';
      CRM.alert(msg, ts('Saved'), 'success');
    }

    /**
     * Handles page rediection after successfully creating quotation.
     *
     * redirects to main quotation list page if no case is selected
     * else redirects to the case view of the selected case.
     */
    function redirectToAppropraitePage () {
      if ($scope.isUpdate) {
        $window.location.href = $window.document.referrer;
        return;
      }

      if (!$scope.salesOrder.case_id) {
        $window.location.href = 'a#/quotations';
      }

      CaseUtils.getDashboardLink($scope.salesOrder.case_id).then(link => {
        $window.location.href = `${link}&tab=Quotations`;
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

    /**
     * Formats a number into the number format of the currently selected currency
     *
     * @param {number} value the number to be formatted
     * @param {string } currency the selected currency
     * @returns {number} the formatted number
     */
    function formatMoney (value, currency) {
      return CRM.formatMoney(value, true, CurrencyCodes.getFormat(currency));
    }
  }
})(angular, CRM.$, CRM._);
