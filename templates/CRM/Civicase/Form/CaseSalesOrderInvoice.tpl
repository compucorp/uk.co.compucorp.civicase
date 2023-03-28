<div class="messages status no-popup">
    {icon icon="fa-info-circle"}{/icon}
    {ts}Your quotation will be emailed to the contact below as a PDF attachment.{/ts}
  </div>
{include file="CRM/Contact/Form/Task/Email.tpl"}

{literal}
<script>
CRM.$(function($) {
  $('#follow-up').hide();
  $('#attachments').parent().parent().hide();
  $('.crm-contactEmail-form-block-campaign_id').hide();
});
</script>
{/literal}