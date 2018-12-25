<!-- <form action="/civicrm/activity/search?reset=1" method="post">
  <input type="text" class="form-control" name="qfKey" value="" />
  <input type="hidden" name="force" value="true" />
  <input type="submit" value="export activities" class="btn btn-default" />
  {$form.hello.html}
</form> -->

<div class="crm-block crm-form-block civicase__locked-contacts__form-block">
  Save report CSV
  {$form.activities.html}
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
