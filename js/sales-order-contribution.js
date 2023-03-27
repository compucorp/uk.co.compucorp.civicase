(function ($, _) {
  $(document).one('crmLoad', function () {
    const params = new URLSearchParams(window.location.search);
    const salesOrderId = params.get('sales_order');
    const salesOrderStatusId = params.get('sales_order_status_id');
    const percentAmount = params.get('percent_amount');

    $('#totalAmount, #totalAmountORaddLineitem, #totalAmountORPriceSet, #price_set_id').hide();
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
      CRM.$('#contact_id').select2('val', caseSalesOrder.client_id).trigger('change');
      CRM.$('#source').val(`Quotation ${caseSalesOrder.id}`).trigger('change');
      CRM.$('#contribution_status_id').val(batch.optionValues[0].value);

      if (Array.isArray(caseSalesOrder.items) && caseSalesOrder.items.length > 0) {
        $('#lineitem-add-block').show().removeClass('hiddenElement');

        let count = 0;
        caseSalesOrder.items.forEach((lineItem, index) => {
          const newQuantity = (percentAmount / 100) * lineItem.quantity;

          addLineItem(count, newQuantity, lineItem.unit_price, lineItem.item_description, lineItem.financial_type_id, lineItem.tax_rate);
          count++;

          if (lineItem.discounted_percentage > 0) {
            const description = `${lineItem.item_description} Discount ${lineItem.discounted_percentage}%`;
            const unitPrice = percent(lineItem.discounted_percentage, lineItem.unit_price);
            addLineItem(count, newQuantity, -unitPrice, description, lineItem.financial_type_id, lineItem.tax_rate);

            count++;
          }
          $('#add-another-item').hide();

          $('input[id="total_amount"]', 'form.CRM_Contribute_Form_Contribution').trigger('change');
        });

        CRM.$(`<input type="hidden" value="${salesOrderId}" name="sales_order" />`).insertBefore('#source');
        CRM.$(`<input type="hidden" value="${salesOrderStatusId}" name="sales_order_status_id" />`).insertBefore('#source');
      }
    }, function (failure) {
    });

    /**
     * @param {number} index Item row index
     * @param {number} quantity Item quantity
     * @param {number} unitPrice Item unit price
     * @param {string} description Item description
     * @param {number} financialTypeId Item financial type
     * @param {number} taxRate Item tax rate
     */
    function addLineItem (index, quantity, unitPrice, description, financialTypeId, taxRate) {
      const row = $($('tr.hiddenElement')[index]);
      row.show().removeClass('hiddenElement');

      $('input[id^="item_label"]', row).val(ts(description));
      $('select[id^="item_financial_type_id"]', row).select2('val', financialTypeId);
      $('input[id^="item_qty"]', row).val(quantity);

      const total = quantity * parseFloat(unitPrice);

      $('input[id^="item_unit_price"]', row).val(CRM.formatMoney(unitPrice, true));
      $('input[id^="item_line_total"]', row).val(CRM.formatMoney(total, true));
      if (taxRate) {
        const taxAmount = percent(taxRate, total);

        $('input[id^="item_tax_amount"]', row).val(CRM.formatMoney(taxAmount, true));
      }
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
