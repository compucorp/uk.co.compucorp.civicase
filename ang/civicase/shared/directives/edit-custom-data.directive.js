(function (angular, $, _, CRM) {
  var module = angular.module('civicase');

  // Editable custom data blocks
  module.directive('civicaseEditCustomData', function ($timeout) {
    return {
      restrict: 'A',
      link: civicaseEditCustomDataLink
    };

    /**
     * Edit Custom Data Link Function.
     *
     * @param {object} scope directive's scope.
     * @param {object} element directive's element reference.
     * @param {object} attrs element attributes.
     */
    function civicaseEditCustomDataLink (scope, element, attrs) {
      var form;

      (function init () {
        element
          .addClass('crm-editable-enabled')
          .on('click', showEditForm);
      })();

      /**
       * Closes the custom data edit form.
       */
      function closeEditForm () {
        form.remove();
        element.show();
        form = null;
      }

      /**
       * Shows the custom data edit form.
       */
      function showEditForm () {
        if (form) {
          return;
        }

        var url = CRM.url('civicrm/case/cd/edit', {
          cgcount: 1,
          action: 'update',
          reset: 1,
          type: 'Case',
          entityID: scope.item.id,
          groupID: scope.customGroup.id,
          cid: scope.item.client[0].contact_id,
          subType: scope.item.case_type_id,
          civicase_reload: scope.caseGetParams()
        });
        form = $('<div></div>').html(element.hide().html());
        form.insertAfter(element)
          .on('click', '.cancel', closeEditForm)
          .on('crmLoad', function () {
            // Workaround bug where href="#" changes the angular route
            $('a.crm-clear-link', form).removeAttr('href');
          })
          .on('crmFormSuccess', function (event, data) {
            scope.$apply(function () {
              scope.pushCaseData(data.civicase_reload[0]);
              closeEditForm();
            });
          });
        CRM.loadForm(url, { target: form });
      }
    }
  });
})(angular, CRM.$, CRM._, CRM);
