<table>
  <tr class="crm-email-element attach-quote">
    <td class="label">{$form.attach_quote.label}</td>
    <td class="html-adjust">{$form.attach_quote.html} <span>Yes</span></td>
  </tr>
</table>

{literal}
  <script type="text/javascript">
    CRM.$(function ($) {
      $('form.CRM_Contribute_Form_Task_Invoice > table.form-layout-compressed > tbody').append($('tr.attach-quote'))
    })
  </script>
{/literal}
