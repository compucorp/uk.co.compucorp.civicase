<div id="case-sales-order-contact-tab">
  <div class="container">
    <quotations-list view="contact" contact-id="{$contactId}"> </quotations-list>
  </div>
</div>
<script type="text/javascript">
  {literal}
    (function(angular, $, _) {
      const app = angular.module('civicaseSalesOrderContactTab', ['civicase-features']);
    })(angular, CRM.$, CRM._);

    CRM.$(document).one('crmLoad', function() {
      angular.bootstrap(document.getElementById('case-sales-order-contact-tab'), ['civicaseSalesOrderContactTab']);
    });
  {/literal}
</script>
