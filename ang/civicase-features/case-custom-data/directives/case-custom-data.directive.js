(function (angular, $, _) {
  var module = angular.module('civicase-features');

  module.directive('civicaseCaseCustomData', function () {
    return {
      restrict: 'E',
      scope: {
        customGroup: '='
      },
      controller: 'civicaseCaseCustomDataController',
      templateUrl: '~/civicase-features/case-custom-data/directives/case-custom-data.directive.html'
    };
  });

  module.controller('civicaseCaseCustomDataController', civicaseCaseCustomDataController);

  /**
   * Injected dependencies for the controller.
   */
  civicaseCaseCustomDataController.$inject = ['$scope', '$element', '$location', 'crmApi4'];

  /**
   * Renders the SearchKit table for a repeatable Case custom group, bound to the
   * current case (entity_id). Row Edit/Delete and the "Add" toolbar use
   * crm-popup links to the group's afform server routes (provisioned separately),
   * so no per-group Angular module needs loading into this SPA.
   *
   * @param {object} $scope the controller scope
   * @param {object} $element the directive's root element
   * @param {object} $location the location service
   * @param {Function} crmApi4 the APIv4 service
   */
  function civicaseCaseCustomDataController ($scope, $element, $location, crmApi4) {
    $scope.loading = true;
    $scope.searchName = null;
    $scope.displayName = null;
    $scope.apiEntity = null;
    $scope.settings = null;
    $scope.filters = {};
    $scope.canAdd = false;
    $scope.addLabel = '';
    $scope.headerLabel = '';
    $scope.openAdd = openAdd;

    var caseId = null;

    // Re-load whenever the active group changes (tabs share one template, so the
    // directive instance is reused when switching between custom-data tabs).
    $scope.$watch('customGroup', function (group) {
      load(group);
    });

    // Any add/edit/delete (incl. SearchKit's own row Delete and inline edits)
    // bubbles a refresh event up to this element's <form>. The SearchKit display
    // re-runs itself off that; we additionally recompute the max-records limit so
    // the "Add" button reappears/disappears as the record count crosses the max.
    $element.on('crmPopupFormSuccess crmFormSuccess', function () {
      $scope.$evalAsync(refreshCanAdd);
    });

    // The wrapping <form> exists only so SearchKit's refresh events bubble to
    // this element; it must never actually submit the page.
    $element.on('submit', function (event) {
      event.preventDefault();
    });

    /**
     * Loads the SearchKit search/display definitions and add access for a group.
     *
     * @param {object} group the active custom group ({name, title})
     */
    function load (group) {
      caseId = $location.search().caseId;

      $scope.loading = true;
      $scope.settings = null;

      if (!group || !caseId) {
        $scope.loading = false;
        return;
      }

      $scope.filters = { entity_id: caseId };
      $scope.searchName = 'Custom_' + group.name + '_Search';
      $scope.displayName = 'Custom_' + group.name + '_Tab';
      $scope.addLabel = ts('Add new %1', { 1: group.title });
      $scope.headerLabel = ts('%1 Details', { 1: group.title });

      crmApi4({
        savedSearch: ['SavedSearch', 'get', {
          select: ['api_entity'], where: [['name', '=', $scope.searchName]]
        }, 0],
        display: ['SearchDisplay', 'get', {
          select: ['settings'],
          where: [['name', '=', $scope.displayName], ['saved_search_id.name', '=', $scope.searchName]]
        }, 0],
        canAdd: ['Custom_' + group.name, 'checkAccess', {
          action: 'create', values: { entity_id: caseId }
        }, 0]
      }).then(function (result) {
        var res = result || {};
        $scope.apiEntity = res.savedSearch ? res.savedSearch.api_entity : null;
        $scope.settings = res.display ? res.display.settings : null;
        $scope.canAdd = !!(res.canAdd && res.canAdd.access);
        $scope.loading = false;
      });
    }

    /**
     * Recomputes whether "Add" should be offered after the table changes (e.g. a
     * row was added/deleted). `Custom_<group>.checkAccess` for the create action
     * is the single source of truth: it applies BOTH the custom-group edit ACL
     * (AC9 — hidden for users who cannot add) AND the max_multiple limit (AC2/AC3
     * — hidden once the maximum is reached, unlimited when unset), exactly as the
     * server enforces them on the actual create.
     */
    function refreshCanAdd () {
      if (!$scope.customGroup || !caseId) {
        return;
      }
      crmApi4('Custom_' + $scope.customGroup.name, 'checkAccess', {
        action: 'create', values: { entity_id: caseId }
      }).then(function (result) {
        $scope.canAdd = !!(result && result[0] && result[0].access);
      });
    }

    /**
     * Opens the create afform for a new record, prefilled with the parent case
     * (entity_id), and refreshes the table + add-limit when it saves.
     */
    function openAdd () {
      if (!$scope.customGroup || !caseId) {
        return;
      }
      // Afform reads prefill args from the URL hash. The parent link for a
      // multi-record custom "create" form is a TOP-LEVEL `entity_id` arg (this
      // mirrors core's admin_ui, which registers the add path as
      // `civicrm/af/custom/<name>/create#?entity_id=[entity_id]`) — NOT nested
      // under the `Record` entity.
      var url = CRM.url('civicrm/af/custom/' + $scope.customGroup.name + '/create') +
        '#?entity_id=' + encodeURIComponent(caseId);
      CRM.loadForm(url).on('crmFormSuccess', function () {
        // Bubble the refresh signal to the <form>: the SearchKit display re-runs
        // and our listener recomputes the add-limit.
        $element.find('form').first().trigger('crmPopupFormSuccess');
      });
    }
  }
})(angular, CRM.$, CRM._);
