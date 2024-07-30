(function (angular) {
  var module = angular.module('civicase');

  module.directive('civicaseCaseDetailsLinkedCasesTab', function () {
    return {
      replace: true,
      restrict: 'E',
      templateUrl: '~/civicase/case/details/linked-cases-tab/directives/case-details-linked-cases-tab.directive.html',
      controller: 'civicaseCaseDetailsLinkedCasesTabController'
    };
  });

  module.controller('civicaseCaseDetailsLinkedCasesTabController', civicaseCaseDetailsLinkedCasesTabController);

  /**
   * Linked Cases Tab Controller.
   *
   * @param {object} $scope the scope object.
   * @param {object} LinkCasesCaseAction the link case action service.
   * @param {object} NewLinkCasesCaseAction the new link case action service.
   * @param {Function} civicaseCrmUrl crm url service.
   * @param {Function} civicaseCrmLoadForm service to load civicrm forms
   */
  function civicaseCaseDetailsLinkedCasesTabController ($scope,
    LinkCasesCaseAction, NewLinkCasesCaseAction, civicaseCrmUrl, civicaseCrmLoadForm) {
    $scope.linkCase = linkCase;
    $scope.newLinkCase = newLinkCase;

    /**
     * Opens a modal that allows the user to link the case stored in the scope with
     * one choosen by the user.
     *
     * The case details are refreshed after linking the cases.
     */
    function linkCase () {
      LinkCasesCaseAction.doAction([$scope.item])
        .then(function (linkCaseForm) {
          civicaseCrmLoadForm(civicaseCrmUrl(linkCaseForm.path, linkCaseForm.query))
            .on('crmFormSuccess crmPopupFormSuccess', function () {
              $scope.refresh();
            });
        });
    }

    /**
     * Opens a modal that allows the user to open a new case and link it at the same time.
     *
     * The case details are refreshed after linking the cases.
     */
    function newLinkCase () {
      NewLinkCasesCaseAction.doAction([$scope.item])
        .then(function (openCaseForm) {
          civicaseCrmLoadForm(civicaseCrmUrl(openCaseForm.path, openCaseForm.query))
            .on('crmFormSuccess crmPopupFormSuccess', function () {
              $scope.refresh();
            });
        });
    }
  }
})(angular);
