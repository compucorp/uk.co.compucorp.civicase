<div id="bootstrap-theme" class="civicase__container" style="background-color: #fff;">

  <div>
    <div class=" panel-body">
      <div class="form-group" ">
        <div class="row">
          <div class="col-sm-12">
            {$form.to_be_invoiced.percent.html}
          </div>
          <div class="col-sm-5">
            {$form.percent_value.html}
          </div>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 3em;">
        <div class="row">
          <div class="col-sm-12">
            {$form.to_be_invoiced.remain.html}
          </div>
        </div>
      </div>

      <div class="form-group">
        <div class="row">
          <label class="col-sm-12 control-label">
            {$form.status.label}
          </label>
          <div class="col-sm-5">
            {$form.status.html}
          </div>
        </div>
      </div>
  </div>
  </div>


  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>

{literal}
<script language="javascript" type="text/javascript">

  CRM.$(function ($) {
    CRM.$('input[name="percent_amount"]').hide();
    if ( CRM.$('input[name="to_be_invoiced"]').val() == 'percent') {
      $('input[name="to_be_invoiced"]#invoice_percent').prop("checked", true);
      CRM.$('input[name="percent_amount"]').show();
    }

    CRM.$('input[name="to_be_invoiced"]').on('input', (e) => {
      if (e.target.value == 'percent') {
        CRM.$('input[name="percent_amount"]').show();
        return
      }

      CRM.$('input[name="percent_amount"]').hide();
    });
  });
</script>
{/literal}
