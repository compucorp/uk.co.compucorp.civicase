<table>
  <tr class="crm-email-element attach-quote">
    <td class="label">{$form.attach_quote.label}</td>
    <td class="html-adjust">{$form.attach_quote.html} <span>Yes</span></td>
  </tr>
</table>

{literal}
  <script type="text/javascript">
    CRM.$(function ($) {
      $('#email_comment').parent().parent().after($('tr.attach-quote'))
    })
  </script>
{/literal}
