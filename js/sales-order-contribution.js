(function ($, _) {
  const waitForElement = function ($, elementPath, callBack) {
    window.setTimeout(function () {
      if ($(elementPath).length) {
        callBack($, $(elementPath));
      } else {
        window.waitForElement($, elementPath, callBack);
      }
    }, 500);
  };

  $(document).one('crmLoad', async function () {
    const params = CRM.vars['uk.co.compucorp.civicase'];
    const salesOrderId = params.sales_order;
    const salesOrderStatusId = params.sales_order_status_id;
    const percentValue = params.percent_value;
    const toBeInvoiced = params.to_be_invoiced;
    const lineItems = JSON.parse(params.line_items);
    const caseCustomField = params.case_custom_field;
    const quotationCustomField = params.quotation_custom_field;
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
      CRM.$.blockUI();
      CRM.$('form#Contribution').css('visibility', 'hidden');
      await new Promise(resolve => setTimeout(resolve, 1000));
      CRM.api4(apiRequest).then(function (batch) {
        const caseSalesOrder = batch.caseSalesOrders[0];

        lineItems.forEach(lineItem => {
          setTimeout(
            () => addLineItem(lineItem.qty, lineItem.unit_price, lineItem.label, lineItem.financial_type_id, lineItem.tax_amount),
            2000
          );
        });

        $('input[id="total_amount"]').trigger('change');
        $('#contribution_status_id').val(batch.optionValues[0].value);
        $('#source').val(`Quotation ${caseSalesOrder.id}`).trigger('change');
        $('#contact_id').select2('val', caseSalesOrder.client_id).trigger('change');
        $(`<input type="hidden" value="${salesOrderId}" name="sales_order" />`).insertBefore('#source');
        $(`<input type="hidden" value="${toBeInvoiced}" name="to_be_invoiced" />`).insertBefore('#source');
        $(`<input type="hidden" value="${percentValue}" name="percent_value" />`).insertBefore('#source');
        $(`<input type="hidden" value="${salesOrderStatusId}" name="sales_order_status_id" />`).insertBefore('#source');
        $(' #totalAmountORaddLineitem, #totalAmountORPriceSet, #price_set_id, #choose-manual').hide();

        waitForElement($, '#customData', function ($, elem) {
          $(`[name^=${caseCustomField}_]`).val(caseSalesOrder.case_id).trigger('change');
          $(`[name^=${quotationCustomField}_]`).val(caseSalesOrder.id).trigger('change');
        });
      }).finally(() => {
        CRM.$.unblockUI();
        CRM.$('form#Contribution').css('visibility', 'visible');
      });
    }

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

      $('input[id^="item_line_total"]', row).val(CRM.formatMoney(total, true));
      $('input[id^="item_tax_amount"]', row).val(taxAmount);
      $('input[id^="item_unit_price"]', row).val(unitPrice).trigger('change');

      count++;
    }
  });
})(CRM.$, CRM._);
