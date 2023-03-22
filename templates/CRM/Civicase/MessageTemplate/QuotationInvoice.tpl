<!DOCTYPE html
  PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
</head>

<body>
  <div style="color: black; margin:16px">
    <table style="width:100%; margin-bottom: 14px;">
      <tbody>
        <tr>
          <td style="text-align:right">
            <img alt="logo"
                src="{$domain_logo}" />
            
          </td>
        </tr>
      </tbody>
    </table>

    <div>
      <table style="width: 100%; margin-bottom: 20px;">
        <thead>
          <tr>
            <th style="text-align: left;">Client Name: {$sales_order.client.display_name}</th>
            <th style="text-align: left;">Date</th>
            <th style="text-align: right;">Address</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <span>{if $sales_order.clientAddress.street_address }{$sales_order.clientAddress.street_address}{/if}</span>
              <br />
              <span>{if $sales_order.clientAddress.supplemental_address_1 }{$sales_order.clientAddress.supplemental_address_1}{/if}</span>
              <br />
              <span>{if $sales_order.clientAddress.supplemental_address_2 }{$sales_order.clientAddress.supplemental_address_2}{/if}</span>
              <br />
              <span>
                {if $sales_order.clientAddress.city}
                  {$sales_order.clientAddress.city} {$sales_order.clientAddress.postal_code}{if $sales_order.clientAddress.postal_code_suffix} - {$sales_order.clientAddress.postal_code_suffix}{/if}<br />
                {/if}
              </span>
            </td>
            <td>
              <span>{$sales_order.quotation_date|crmDate}</span>
              <p><strong>Quote Number</strong></p>
              <p>{$sales_order.id}</p>
            </td>
            <td style="text-align: right;">
              <span>{domain.address}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div>
      <p><strong>Description</strong></p>

      <p>{$sales_order.description}</p>

      <table style="width:100%; margin-bottom: 14px;">
      </table>
    </div>

    <div class="table" style="margin-bottom: 14px; ">
      <table rules="cols" style="width:100%; border: 1px solid; text-align: center;">
        <thead>
          <tr class="head" style="background-color: #cac9c9;">
            <th style="padding: 8px 10px; text-align: center;">Description</th>
            <th style="padding: 8px 10px; text-align: center;">Quantity</th>
            <th style="padding: 8px 10px; text-align: center;">Unit Price</th>
            <th style="padding: 8px 10px; text-align: center;">Discount</th>
            <th style="padding: 8px 10px; text-align: center;">VAT</th>
            <th style="padding: 8px 10px; text-align: center;">Amount {$sales_order.currency} (without tax)</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$sales_order.items key=k item=item}
          <tr {if ($k%2) == 0} style="background-color: #e0e0e0;" {/if}>
            <td style="padding: 8px 10px; text-align: center;" >{$item.item_description}</td>
            <td style="padding: 8px 10px; text-align: center;">{$item.quantity}</td>
            <td style="padding: 8px 10px; text-align: center;">{$item.unit_price|crmMoney:$sales_order.currency}</td>
            <td style="padding: 8px 10px; text-align: center;">{if empty($item.discounted_percentage) } 0 {else}{$item.discounted_percentage}{/if}%</td>
            <td style="padding: 8px 10px; text-align: center;">{if empty($item.tax_rate) } 0 {else}{$item.tax_rate}{/if}%</td>
            <td style="padding: 8px 10px; text-align: center;">{$item.subtotal_amount|crmMoney:$sales_order.currency}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>

    <div style="margin-bottom: 14px; overflow: auto;">
      <div style="margin-left: 300px;">
        <table style="width: 100%;">
          <tbody>
            <tr>
              <td>SubTotal (inclusive of discount)</td>
              <td style="text-align: left;">{$sales_order.total_before_tax|crmMoney:$sales_order.currency}</td>
            </tr>
          </tbody>
          {foreach from=$sales_order.taxRates item=tax}
          <tbody>
            <tr>
              <td>Total VAT ({$tax.rate}%)</td>
              <td style="text-align: left;">{$tax.value|crmMoney:$sales_order.currency}</td>
            </tr>
          </tbody>
          {/foreach}
          <tbody>
            <tr class="total-row">
              <td style="border-top: 1px solid #000; border-bottom: 1px solid #000;">Total Amount</td>
              <td style="border-top: 1px solid #000; border-bottom: 1px solid #000; text-align: left;">{$sales_order.total_after_tax|crmMoney:$sales_order.currency}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top: 16px;">
      <p><strong>Terms</strong></p>

      <p>{if $terms }{$terms}{/if}</p>

      <table style="width:100%; margin-bottom: 14px;">
      </table>
    </div>
  </div>
</body>

</html>
