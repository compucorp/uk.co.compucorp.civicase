(function ($, _) {
  $('input[value=email_invoice]').prop('checked', true).click();
  $('div.help').hide();
  $('input[name=output]').parent().parent().hide();
})(CRM.$, CRM._);
