<table>
  <tr class="crm-email-element attach-quote">
    <td class="label">{$form.attach_quote.label}</td>
    <td class="html-adjust">{$form.attach_quote.html} {if isset($form.attach_quote.html)}<span>Yes</span>{/if}</td>
  </tr>
</table>
<div id="editMessageDetails"></div>

{literal}
  <script type="text/javascript">
    CRM.$(function ($) {
      $('#attach_quote').prop('checked', 1)
      if ($('#html_message').length) {
        $('#html_message').parent().parent().after($('tr.attach-quote'))

        return
      }
      $('#email_comment').parent().parent().after($('tr.attach-quote'))
    })
  </script>
{/literal}
