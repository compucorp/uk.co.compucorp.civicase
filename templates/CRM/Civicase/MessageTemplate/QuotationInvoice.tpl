<!DOCTYPE html
  PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <title></title>
</head>

<body>
  <div style="padding-top:100px;margin-right:50px;border-style: none; font-family: Arial, Verdana, sans-serif;">
    <table style="margin-top:5px;padding-bottom:50px;" cellpadding="5" cellspacing="0">
      <tr>
        <td><img src="{$domain_logo}" height="34px" width="99px"></td>
      </tr>
    </table>

    <div>
      <table style="font-family: Arial, Verdana, sans-serif;" width="100%" height="100" border="0" cellpadding="5" cellspacing="0">
        <tr>
          <td width="30%"><b><font size="1" align="center">{ts}Client Name: {$sales_order.client.display_name}<{/ts}</font></b></td>
          <td width="50%" valign="bottom"><b><font size="1" align="center">{ts}Date:{/ts}</font></b></td>
          <td valign="bottom" style="white-space: nowrap"><b><font size="1" align="right">Address</font></b></td>
        </tr>
        <tr>
          <td><font size="1" align="center">
          {if $sales_order.clientAddress.street_address }{$sales_order.clientAddress.street_address}{/if}
          {if $sales_order.clientAddress.supplemental_address_1 }{$sales_order.clientAddress.supplemental_address_1}{/if}
          </font></td>
          <td><font size="1" align="right">{$sales_order.quotation_date|crmDate}</font></td>
          <td style="white-space: nowrap"><font size="1" align="right">
              {if !empty($domain_location.street_address) }{$domain_location.street_address}{/if}
              {if !empty($domain_location.supplemental_address_1) }{$domain_location.supplemental_address_1}{/if}
          </font></td>
        </tr>
        <tr>
          <td><font size="1" align="center">
          {if $sales_order.clientAddress.supplemental_address_2  }{$sales_order.clientAddress.supplemental_address_2 }{/if}
          {if $sales_order.clientAddress.state }{$sales_order.clientAddress.state}{/if}
          </font></td>
          <td><b><font size="1" align="right">Quote Number</font></b></td>
          <td style="white-space: nowrap"><font size="1" align="right">
              {if !empty($domain_location.supplemental_address_2)  }{$domain_location.supplemental_address_2 }{/if}
              {if !empty($domain_location.state) }{$domain_location.state}{/if}
          </font></td>
        </tr>
        <tr>
          <td><font size="1" align="center">
          {if $sales_order.clientAddress.city  }{$sales_order.clientAddress.city }{/if}
          {if $sales_order.clientAddress.postal_code }{$sales_order.clientAddress.postal_code}{/if}
          </font></td>
          <td><font size="1" align="right">{$sales_order.id}</font></td>
          <td style="white-space: nowrap"><font size="1" align="right">
              {if !empty($domain_location.city) }{$domain_location.city }{/if}
              {if !empty($domain_location.postal_code) }{$domain_location.postal_code}{/if}
          </font></td>
        </tr>
        <tr>
          <td><font size="1" align="center">
          {if $sales_order.clientAddress.country  }{$sales_order.clientAddress.country}{/if}
          </font></td>
          <td></td>
          <td style="white-space: nowrap"><font size="1" align="right">
              {if !empty($domain_location.country) }{$domain_location.country }{/if}
          </font></td>
        </tr>
      </table>

    <div style="margin-top: 50px;">
      <table style="font-family: Arial, Verdana, sans-serif;" width="100%" height="100" border="0" cellpadding="5" cellspacing="0">
        <tr> <td><font size="1"><strong>Description</strong></font></td> </tr>
        <tr> <td><font size="1">{$sales_order.description}</font></td> </tr>
      </table>
    </div>

    <div class="table" style="margin-bottom: 14px; ">
      <table rules="cols" style="padding-top:25px; border: none" width="100%" border="0" cellpadding="5" cellspacing="0">
        <thead>
          <tr class="head" style="background-color: #cbcbcd;">
            <th style="padding: 8px 10px; border: 1px solid #000; text-align: left; font-weight:bold;width:100%"><font size="1">{ts}Description{/ts}</font></th>
            <th style="padding: 8px 10px; border: 1px solid #000; text-align:right;font-weight:bold;white-space: nowrap;"><font size="1">{ts}Quantity{/ts}</font></th>
            <th style="padding: 8px 10px; border: 1px solid #000; text-align:right;font-weight:bold;white-space: nowrap;"><font size="1">{ts}Unit Price{/ts}</font></th>
            <th style="padding: 8px 10px; border: 1px solid #000; text-align:right;font-weight:bold;white-space: nowrap;"><font size="1">{ts}Discount{/ts}</font></th>
            <th style="padding: 8px 10px; border: 1px solid #000; text-align:right;font-weight:bold;white-space: nowrap;"><font size="1">{ts}VAT{/ts}</font></th>
            <th style="padding: 8px 10px; border: 1px solid #000; text-align:right;font-weight:bold;white-space: nowrap;"><font size="1">{ts}Amount {$sales_order.currency} (without tax){/ts}</font></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$sales_order.items key=k item=item}
          <tr {if ($k%2) == 0} style="background-color: #eeeeee;" {/if}>
            <td style="padding: 8px 10px; text-align: left; white-space: nowrap; border: 1px solid #000;" > <font size="1">{$item.item_description|truncate:30:"..."}</font></td>
            <td style="padding: 8px 10px;text-align: right;border: 1px solid #000;"><font size="1">{$item.quantity}</font></td>
            <td style="padding: 8px 10px;text-align: right;border: 1px solid #000;"><font size="1">{$item.unit_price|crmMoney:$sales_order.currency}</font></td>
            <td style="padding: 8px 10px;text-align: right;border: 1px solid #000;"><font size="1">{if empty($item.discounted_percentage) } 0 {else}{$item.discounted_percentage}{/if}%</font></td>
            <td style="padding: 8px 10px;text-align: right;border: 1px solid #000;"><font size="1">{if empty($item.tax_rate) } 0 {else}{$item.tax_rate}{/if}%</font></td>
            <td style="padding: 8px 10px;text-align: right;border: 1px solid #000;"><font size="1">{$item.subtotal_amount|crmMoney:$sales_order.currency}</font></td>
          </tr>
          {/foreach}
          <tr>
            <td colspan="3" style="border: none;"></td>
            <td colspan="2" style="text-align:right;white-space: nowrap;  border: none;"><font size="1">{ts}SubTotal (inclusive of discount){/ts}</font></td>
            <td style="text-align:right; border: none;"><font size="1">{$sales_order.total_before_tax|crmMoney:$sales_order.currency}</font></td>
          </tr>
          {foreach from=$sales_order.taxRates item=tax}
            <tr>
              <td colspan="3" style="border: none;"></td>
              <td colspan="2" style="text-align:right;white-space: nowrap; border: none;"><font size="1">{ts}Total VAT ({$tax.rate}%){/ts}</font></td>
              <td style="text-align:right;white-space: nowrap; border: none;"><font size="1">{$tax.value|crmMoney:$sales_order.currency}</font></td>
            </tr>
          {/foreach}
            <tr>
              <td colspan="3" style="border: none;"></td>
              <td colspan="3" style="border: none;"><hr></hr></td>
            </tr>
            <tr>
              <td colspan="3" style="border: none;"></td>
              <td colspan="2" style="text-align:right;white-space: nowrap; border: none;"><b><font size="1">{ts}Total Amount{/ts}</font></b></td>
              <td style="text-align:right;white-space: nowrap; border: none;"><font size="1">{$sales_order.total_after_tax|crmMoney:$sales_order.currency}</font></td>
            </tr>
        </tbody>
      </table>
    </div>

    {if !empty($terms) }
    <div style="margin-top: 75px;">
      <table style=""padding-top:25px; font-family: Arial, Verdana, sans-serif;" width="100%" height="100" border="0" cellpadding="5" cellspacing="0">
        <tr> <td><font size="1"><strong>Terms</strong></font></td> </tr>
        <tr> <td><font size="1">{$terms}</font></td> </tr>
      </table>
    </div>
    {/if}
  </div>
</body>

</html>
