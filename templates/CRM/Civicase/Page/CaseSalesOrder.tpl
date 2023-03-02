<div id="case-sales-order-view">
  <div class="container">
    <quotation-list-view> </quotation-list-view>
  </div>
</div>
<script type="text/javascript">
  const id = { $sales_order_id };
  {literal}
    (function(angular, $, _) {
      const app = angular.module('civicaseSalesOrderView', ['civicase-features']);
      app.directive('quotationListView', function () {
        return {
          template: `<quotations-view sales-order-id="${id}"></quotations-view>`
        }
      })
      
      
    })(angular, CRM.$, CRM._);

    CRM.$(document).one('crmLoad', function() {
      angular.bootstrap(document.getElementById('case-sales-order-view'), ['civicaseSalesOrderView']);
    });
  {/literal}
</script>
