(function ($, _) {
  $(document).one('crmLoad', function () {
    const params = CRM.vars['uk.co.compucorp.civicase'];
    const salesOrderId = params.sales_order;
    const salesOrderStatusId = params.sales_order_status_id;
    const percentValue = params.percent_value;
    const toBeInvoiced = params.to_be_invoiced;
    const PERCENT = 'percent';
    const REMAIN = 'remain';
    let count = 0;

    if ($('input[name="sales_order"]').length) { $('#totalAmount, #totalAmountORaddLineitem, #totalAmountORPriceSet, #price_set_id').hide(); }
    $('#total_amount').val(0);

    const apiRequest = {};
    apiRequest.caseSalesOrders = ['CaseSalesOrder', 'get', {
      select: ['*', 'case_sales_order_line.*'],
      where: [['id', '=', salesOrderId]],
      chain: { items: ['CaseSalesOrderLine', 'get', { where: [['sales_order_id', '=', '$id']], select: ['*', 'product_id.name', 'financial_type_id.name'] }] }
    }];

    apiRequest.optionValues = ['OptionValue', 'get', {
      select: ['value'],
      where: [['option_group_id:name', '=', 'contribution_status'], ['name', '=', 'pending']]
    }];

    CRM.api4(apiRequest).then(function (batch) {
      const caseSalesOrder = batch.caseSalesOrders[0];

      $('#add-another-item').hide();
      $('#lineitem-add-block').show().removeClass('hiddenElement');
      $('#contribution_status_id').val(batch.optionValues[0].value);
      $('#source').val(`Quotation ${caseSalesOrder.id}`).trigger('change');
      $('#contact_id').select2('val', caseSalesOrder.client_id).trigger('change');

      if (!Array.isArray(caseSalesOrder.items) || caseSalesOrder.items.length < 1) {
        return;
      }

      prefillLineItemsFromCaseSalesOrder(caseSalesOrder);

      if (toBeInvoiced === REMAIN) {
        // For remaining invoice, we need to subtract previously created invoice from it.
        // get previous contribution associated with this sales order
        // loop through its line items and create them as a negative value
        // check if the total is still greater than zero.

        prefilLineItemsFromPreviousContribution(caseSalesOrder);
      }

      $(`<input type="hidden" value="${salesOrderId}" name="sales_order" />`).insertBefore('#source');
      $(`<input type="hidden" value="${toBeInvoiced}" name="to_be_invoiced" />`).insertBefore('#source');
      $(`<input type="hidden" value="${percentValue}" name="percent_value" />`).insertBefore('#source');
      $(`<input type="hidden" value="${salesOrderStatusId}" name="sales_order_status_id" />`).insertBefore('#source');
    }, function (failure) {
    });

    /**
     * Prefills the contribution form with the sales order line items.
     *
     * @param {object} caseSalesOrder The case sales order object
     */
    function prefillLineItemsFromCaseSalesOrder (caseSalesOrder) {
      caseSalesOrder.items.forEach((lineItem, index) => {
        const newQuantity = (toBeInvoiced === PERCENT) ? (percentValue / 100) * lineItem.quantity : lineItem.quantity;

        addLineItem(newQuantity, lineItem.unit_price, lineItem.item_description, lineItem.financial_type_id, lineItem.tax_rate);

        if (lineItem.discounted_percentage > 0) {
          const description = `${lineItem.item_description} Discount ${lineItem.discounted_percentage}%`;
          const unitPrice = percent(lineItem.discounted_percentage, lineItem.unit_price);
          addLineItem(newQuantity, -unitPrice, description, lineItem.financial_type_id, lineItem.tax_rate);
        }

        $('input[id="total_amount"]', 'form.CRM_Contribute_Form_Contribution').trigger('change');
      });
    }

    /**
     * Prefills the contribution form with the line items from previous contribution.
     *
     * This value of this line items will be added as negative value, hence they
     * will be deducted from the total value of the current invoice.
     *
     * @param {object} caseSalesOrder The case sales order object
     */
    function prefilLineItemsFromPreviousContribution (caseSalesOrder) {
      CRM.api4({
        caseSalesOrderContributions: ['CaseSalesOrderContribution', 'get', {
          select: ['contribution_id'],
          where: [['case_sales_order_id.id', '=', caseSalesOrder.id]],
          chain: { items: ['LineItem', 'get', { where: [['contribution_id', '=', '$contribution_id']] }] }
        }]
      }).then(function (batch) {
        if (Array.isArray(batch.caseSalesOrderContributions)) {
          batch.caseSalesOrderContributions.forEach(previousContribution => {
            if (!previousContribution.items || !Array.isArray(previousContribution.items) || previousContribution.items.length < 1) {
              return;
            }

            previousContribution.items.forEach(item => {
              addLineItem(item.qty, -item.unit_price, item.label, item.financial_type_id, { amount: item.tax_amount });
            });

            $('input[id="total_amount"]', 'form.CRM_Contribute_Form_Contribution').trigger('change');
          });
        }
      }, function (failure) {
      });
    }

    /**
     * @param {number} quantity Item quantity
     * @param {number} unitPrice Item unit price
     * @param {string} description Item description
     * @param {number} financialTypeId Item financial type
     * @param {number|object} taxRate Item tax rate
     */
    function addLineItem (quantity, unitPrice, description, financialTypeId, taxRate) {
      const row = $($(`tr#add-item-row-${count}`));
      row.show().removeClass('hiddenElement');

      $('input[id^="item_label"]', row).val(ts(description));
      $('select[id^="item_financial_type_id"]', row).select2('val', financialTypeId);
      $('input[id^="item_qty"]', row).val(quantity);

      const total = quantity * parseFloat(unitPrice);

      $('input[id^="item_unit_price"]', row).val(CRM.formatMoney(unitPrice, true));
      $('input[id^="item_line_total"]', row).val(CRM.formatMoney(total, true));

      let taxAmount = taxRate.amount;
      if (!isNaN(taxRate)) {
        taxAmount = percent(taxRate, total);
      }
      $('input[id^="item_tax_amount"]', row).val(CRM.formatMoney(taxAmount, true));

      count++;
    }

    /**
     * Returns percentage% of value
     * e.g. 5% of 10.
     *
     * @param {number} percentage Percentage to calculate
     * @param {number} value The value to get percentage of
     *
     * @returns {number} Calculated Percentage in float
     */
    function percent (percentage, value) {
      return (parseFloat(percentage) / 100) * parseFloat(value);
    }
  });
})(CRM.$, CRM._);
