(function ($, _) {
  $(document).one('crmLoad', function () {
    const params = CRM.vars['uk.co.compucorp.civicase'];
    const salesOrderId = params.sales_order;
    const salesOrderStatusId = params.sales_order_status_id;
    const percentValue = params.percent_value;
    const toBeInvoiced = params.to_be_invoiced;
    const lineItems = JSON.parse(params.line_items);
    let count = 0;

    const apiRequest = {};
    apiRequest.caseSalesOrders = ['CaseSalesOrder', 'get', {
      select: ['*'],
      where: [['id', '=', salesOrderId]]
    }];
    apiRequest.optionValues = ['OptionValue', 'get', {
      select: ['value'],
      where: [['option_group_id:name', '=', 'contribution_status'], ['name', '=', 'pending']]
    }];

    if (Array.isArray(lineItems)) {
      CRM.api4(apiRequest).then(function (batch) {
        const caseSalesOrder = batch.caseSalesOrders[0];

        lineItems.forEach(lineItem => {
          addLineItem(lineItem.qty, lineItem.unit_price, lineItem.label, lineItem.financial_type_id, lineItem.tax_amount);
        });

        $('#total_amount').val(0);
        $('#lineitem-add-block').show().removeClass('hiddenElement');
        $('#contribution_status_id').val(batch.optionValues[0].value);
        $('#source').val(`Quotation ${caseSalesOrder.id}`).trigger('change');
        $('#contact_id').select2('val', caseSalesOrder.client_id).trigger('change');
        $('input[id="total_amount"]', 'form.CRM_Contribute_Form_Contribution').trigger('change');
        $(`<input type="hidden" value="${salesOrderId}" name="sales_order" />`).insertBefore('#source');
        $(`<input type="hidden" value="${toBeInvoiced}" name="to_be_invoiced" />`).insertBefore('#source');
        $(`<input type="hidden" value="${percentValue}" name="percent_value" />`).insertBefore('#source');
        $(`<input type="hidden" value="${salesOrderStatusId}" name="sales_order_status_id" />`).insertBefore('#source');
        $('#totalAmount, #totalAmountORaddLineitem, #totalAmountORPriceSet, #price_set_id, #choose-manual, .remove_item, #add-another-item').hide();
      });
    }

    $("a[target='crm-popup']").on('crmPopupFormSuccess', function (e) {
      CRM.refreshParent(e);
    });

    /**
     * @param {number} quantity Item quantity
     * @param {number} unitPrice Item unit price
     * @param {string} description Item description
     * @param {number} financialTypeId Item financial type
     * @param {number|object} taxAmount Item tax amount
     */
    function addLineItem (quantity, unitPrice, description, financialTypeId, taxAmount) {
      const row = $($(`tr#add-item-row-${count}`));
      row.show().removeClass('hiddenElement');
      quantity = +parseFloat(quantity).toFixed(10); // limit to 10 decimal places

      $('input[id^="item_label"]', row).val(ts(description));
      $('select[id^="item_financial_type_id"]', row).select2('val', financialTypeId);
      $('input[id^="item_qty"]', row).val(quantity);

      const total = quantity * parseFloat(unitPrice);

      $('input[id^="item_unit_price"]', row).val(unitPrice);
      $('input[id^="item_line_total"]', row).val(CRM.formatMoney(total, true));

      $('input[id^="item_tax_amount"]', row).val(taxAmount);

      count++;
    }
  });
})(CRM.$, CRM._);
