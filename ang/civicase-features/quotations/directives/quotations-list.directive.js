(function (angular, _, $) {
  var module = angular.module('civicase-features');

  module.directive('quotationsList', function () {
    return {
      restrict: 'E',
      controller: 'quotationsListController',
      templateUrl: '~/civicase-features/quotations/directives/quotations-list.directive.html',
      scope: {
        view: '@',
        contactId: '@'
      }
    };
  });

  module.controller('quotationsListController', quotationsListController);

  /**
   * @param {object} $scope the controller scope
   * @param {object} $location the location service
   * @param {object} $window window object of the browser
   */
  function quotationsListController ($scope, $location, $window) {
    $scope.redirectToQuotationCreationScreen = redirectToQuotationCreationScreen;

    (function init () {
      addEventToElementsWhenInDOMTree();
    }());

    /**
     * Redirects user to new quotation screen
     */
    function redirectToQuotationCreationScreen () {
      let url = '/civicrm/case-features/a#/quotations/new';
      const caseId = $location.search().caseId;
      if (caseId) {
        url += `?caseId=${caseId}`;
      }

      $window.location.href = url;
    }

    /**
     * Add events to elements that are occasionally removed from DOM tree
     */
    function addEventToElementsWhenInDOMTree () {
      const observer = new window.MutationObserver(function (mutations) {
        if ($('#ui-datepicker-div:visible a').length) {
          // Prevents date picker from triggering route navigation.
          $('#ui-datepicker-div:visible a').click((event) => { event.preventDefault(); });
        }

        if ($('.civicase__features-filters-clear').length) {
          // Handle clear filter button.
          $('.civicase__features-filters-clear').click(event => {
            CRM.$('.civicase__features input, .civicase__features textarea').val('').change();
          });
        }
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    }
  }
})(angular, CRM._, CRM.$);
