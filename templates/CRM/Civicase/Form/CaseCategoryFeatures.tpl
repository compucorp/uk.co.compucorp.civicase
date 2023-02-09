<script type="text/javascript">
    {literal}
    CRM.$(function($) {
      CRM.$('#case_category_features').insertAfter(CRM.$('#case_category_instance_type'));
    });
    {/literal}
</script>

<table>
  <tr id="case_category_features">
    <td class="label"> Additional Features </td>
    <td>
      <table class="selector">
        {foreach from=$features item=row}
          <tr>
            <td>{$form.$row.html} {$form.$row.label}</td>
            </tr>
        {/foreach}
      </table>
    </td>
  </tr>
</table>
