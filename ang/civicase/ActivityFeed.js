(function (angular, $, _) {
  var module = angular.module('civicase');

  module.config(function ($routeProvider) {
    $routeProvider.when('/activity/feed', {
      reloadOnSearch: false,
      template: '<div id="bootstrap-theme" class="civicase__container" civicase-activity-feed="{}"></div>'
    });
  });

  module.directive('civicaseActivityFeed', function () {
    return {
      restrict: 'A',
      templateUrl: '~/civicase/ActivityFeed.html',
      controller: civicaseActivityFeedController,
      scope: {
        params: '=civicaseActivityFeed',
        showBulkActions: '=',
        caseTypeId: '=',
        refreshCase: '=?refreshCallback'
      }
    };
  });

  module.controller('civicaseActivityFeedController', civicaseActivityFeedController);

  function civicaseActivityFeedController ($scope, $q, BulkActions, crmApi, crmUiHelp, crmThrottle, formatActivity, $rootScope, dialogService, ContactsDataService) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('civicase');
    var ITEMS_PER_PAGE = 25;
    var caseId = $scope.params ? $scope.params.case_id : null;
    var pageNum = 0;
    var allActivities = [];

    $scope.isLoading = true;
    $scope.activityTypes = CRM.civicase.activityTypes;
    $scope.activityStatuses = CRM.civicase.activityStatuses;
    $scope.activities = {};
    $scope.activityGroups = [];
    $scope.bulkAllowed = $scope.showBulkActions && BulkActions.isAllowed();
    $scope.remaining = true;
    $scope.selectedActivities = [];
    $scope.viewingActivity = {};
    $scope.caseTimelines = $scope.caseTypeId ? _.sortBy(CRM.civicase.caseTypes[$scope.caseTypeId].definition.activitySets, 'label') : [];

    (function init () {
      bindRouteParamsToScope();
      initiateWatchersAndEvents();
    }());

    /**
     * Toggle Bulk Actions checkbox of the given activity
     */
    $scope.toggleSelected = function (activity) {
      if ($scope.selectedActivities.indexOf(activity.id) === -1) {
        $scope.selectedActivities.push(activity.id);
      } else {
        _.remove($scope.selectedActivities, function (activityID) {
          return activityID === activity.id;
        });
      }
    };

    /**
     * Load next set of activities
     */
    $scope.nextPage = function () {
      ++pageNum;
      getActivities(true);
    };

    /**
     * Refresh Activities
     * If: refreshCase callback is passed to the directive, calls the same
     * Else: Calls crmApi directly
     */
    $scope.refreshAll = function (apiCalls) {
      if (_.isFunction($scope.refreshCase)) {
        $scope.refreshCase(apiCalls);
      } else {
        if (!_.isArray(apiCalls)) {
          apiCalls = [];
        }

        crmApi(apiCalls, true).then(function (result) {
          getActivities(false);
        });
      }
    };

    /**
     * View an activity in details view
     *
     * @param {int} id
     * @param {Event} e
     */
    $scope.viewActivity = function (id, e) {
      if (e && $(e.target).closest('a, button').length) {
        return;
      }
      var act = _.find($scope.activities, {id: id});
      // If the same activity is selected twice, it's a deselection. If the activity doesn't exist, abort.
      if (($scope.viewingActivity && $scope.viewingActivity.id === id) || !act) {
        $scope.viewingActivity = {};
        $scope.aid = 0;
      } else {
        // Mark email read
        if (act.status === 'Unread') {
          var statusId = _.findKey(CRM.civicase.activityStatuses, {name: 'Completed'});
          crmApi('Activity', 'setvalue', {id: act.id, field: 'status_id', value: statusId}).then(function () {
            act.status_id = statusId;
            formatActivity(act);
          });
        }
        $scope.viewingActivity = _.cloneDeep(act);
        $scope.$emit('civicase::activity-card::load-activity-form', $scope.viewingActivity);
        $scope.aid = act.id;
      }
    };

    /**
     * Binds all route parameters to scope
     */
    function bindRouteParamsToScope () {
      $scope.$bindToRoute({ param: 'aid', expr: 'aid', format: 'raw', default: 0 });
      $scope.$bindToRoute({ expr: 'filters', param: 'af', default: {} });
      $scope.$bindToRoute({
        expr: 'displayOptions',
        param: 'ado',
        default: angular.extend({}, {
          followup_nested: true,
          overdue_first: true,
          include_case: true
        }, $scope.params.displayOptions || {})
      });
    }

    /**
     * Bulk Selection Event Listener
     *
     * @params {Object} event
     * @params {String} condition
     */
    function bulkSelectionsListener (event, condition) {
      if (condition === 'none') {
        deselectAllActivities();
      } else if (condition === 'visible') {
        selectDisplayedActivities();
      } else if (condition === 'all') {
        selectEveryActivity();
      }
    }

    /**
     * Deselection of all activities
     */
    function deselectAllActivities () {
      $scope.selectedActivities = [];
    }

    /**
     * Select all Activity
     */
    function selectEveryActivity () {
      $scope.selectedActivities = [];
      $scope.selectedActivities = allActivities.map(function (activity) {
        return activity.id;
      });
      selectDisplayedActivities(); // Update the UI model with displayed cases selected;
    }

    /**
     * Select All visible data.
     */
    function selectDisplayedActivities () {
      var isCurrentActivityInSelectedCases;

      _.each($scope.activities, function (activity) {
        isCurrentActivityInSelectedCases = _.find($scope.selectedActivities, function (activityID) {
          return activityID === activity.id;
        });

        if (!isCurrentActivityInSelectedCases) {
          $scope.selectedActivities.push(activity.id);
        }
      });
    }

    /**
     * Groups the activities into Overdue/Future/Past
     *
     * @param {Array} activities
     * @return {Array}
     */
    function groupActivities (activities) {
      var group, overdue, upcoming, past;
      var groups = [];
      var date = new Date();
      var now = CRM.utils.formatDate(date, 'yy-mm-dd') + ' ' + date.toTimeString().slice(0, 8);

      if ($scope.displayOptions.overdue_first) {
        groups.push(overdue = {key: 'overdue', title: ts('Overdue Activities'), activities: []});
      }

      groups.push(upcoming = {key: 'upcoming', title: ts('Future Activities'), activities: []});
      groups.push(past = {key: 'past', title: ts('Past Activities - Prior to Today'), activities: []});
      _.each(activities, function (act) {
        if (act.activity_date_time > now) {
          group = upcoming;
        } else if (overdue && act.is_overdue) {
          group = overdue;
        } else {
          group = past;
        }
        group.activities.push(act);
      });

      return groups;
    }

    /**
     * Fetch additional information about the contacts
     *
     * @param {array} cases
     */
    function fetchContactsData (activities) {
      var contacts = [];

      _.each(activities, function (activity) {
        contacts = contacts.concat(activity.assignee_contact_id);
        contacts = contacts.concat(activity.target_contact_id);
        contacts.push(activity.source_contact_id);
      });

      return ContactsDataService.add(contacts);
    }

    /**
     * Get all activities
     *
     * @param {Boolean} nextPage
     */
    function getActivities (nextPage) {
      if (nextPage !== true) {
        deselectAllActivities();
        pageNum = 0;
      }

      crmThrottle(loadActivities).then(function (result) {
        var newActivities = _.each(result[0].acts.values, formatActivity);
        allActivities = result[0].all.values;
        var remaining = allActivities.length - (ITEMS_PER_PAGE * (pageNum + 1));

        if (pageNum) {
          $scope.activities = $scope.activities.concat(newActivities);
        } else {
          $scope.activities = newActivities;
        }
        $scope.activityGroups = groupActivities($scope.activities);
        $scope.totalCount = allActivities.length;
        $scope.remaining = remaining > 0 ? remaining : 0;
        if (!allActivities.length && !pageNum) {
          $scope.remaining = false;
        }
        // reset viewingActivity to get latest data
        $scope.viewingActivity = {};
        $scope.viewActivity($scope.aid);
        $scope.isLoading = false;
      });
    }

    /**
     * Load activities
     *
     * @return {Promise}
     */
    function loadActivities () {
      var returnParams = {
        sequential: 1,
        return: [
          'subject', 'details', 'activity_type_id', 'status_id',
          'source_contact_name', 'target_contact_name', 'assignee_contact_name',
          'activity_date_time', 'is_star', 'original_id', 'tag_id.name', 'tag_id.description',
          'tag_id.color', 'file_id', 'is_overdue', 'case_id', 'priority_id'
        ],
        options: {
          sort: ($scope.displayOptions.overdue_first ? 'is_overdue DESC, ' : '') + 'activity_date_time DESC',
          limit: ITEMS_PER_PAGE,
          offset: ITEMS_PER_PAGE * pageNum
        }
      };
      var params = {
        is_current_revision: 1,
        is_deleted: 0,
        is_test: 0,
        options: {}
      };
      if (caseId) {
        params.case_id = caseId;
      } else if (!$scope.displayOptions.include_case) {
        params.case_id = {'IS NULL': 1};
      } else {
        returnParams.return = returnParams.return.concat(['case_id.case_type_id', 'case_id.status_id', 'case_id.contacts']);
      }

      _.each($scope.filters, function (val, key) {
        if (key[0] === '@') return; // Virtual params.
        if (key === 'activity_type_id' || key === 'activitySet') {
          setActivityTypeIDsFilter(params);
        } else if (val) {
          if (key === 'text') {
            params.subject = {LIKE: '%' + val + '%'};
            params.details = {LIKE: '%' + val + '%'};
            params.options.or = [['subject', 'details']];
          } else if (_.isString(val)) {
            params[key] = {LIKE: '%' + val + '%'};
          } else if (_.isArray(val) && val.length) {
            params[key] = val.length === 1 ? val[0] : {IN: val};
          } else if (!_.isArray(val)) {
            params[key] = val;
          }
        }
      });
      $rootScope.$broadcast('civicaseActivityFeed.query', $scope.filters, params);
      if ($scope.params && $scope.params.filters) {
        angular.extend(params, $scope.params.filters);
      }

      return crmApi({
        acts: ['Activity', 'get', $.extend(true, returnParams, params)],
        all: ['Activity', 'get', $.extend(true, {
          sequential: 1,
          return: ['id'],
          options: { limit: 0 }}, params)] // all activities, also used to get count
      }).then(function (result) {
        return $q.all([
          result,
          fetchContactsData(result.acts.values)
        ]);
      });
    }

    /**
     * Initiate watchers and event handlers
     */
    function initiateWatchersAndEvents () {
      $scope.$watchCollection('filters', getActivities);
      $scope.$watchCollection('displayOptions', getActivities);
      $scope.$watch('params.filters', getActivities, true);
      $scope.$on('updateCaseData', getActivities);
      $scope.$on('civicase::bulk-actions::bulk-selections', bulkSelectionsListener);
      $scope.$on('civicaseAcitivityClicked', function (event, $event, activity) {
        $scope.viewActivity(activity.id, $event);
      });
    }

    /**
     * When timeline/activity-set filter is applied,
     * gets the activity type ids from the selected timeline.
     * Also adds those activity types ids with the "Activity Type" filter
     *
     * @param {*} params
     */
    function setActivityTypeIDsFilter (params) {
      var activityTypeIDs = [];

      if ($scope.filters.activitySet) {
        var activitySet = _.find($scope.caseTimelines, function (activitySet) {
          return activitySet.name === $scope.filters.activitySet;
        });

        if (activitySet) {
          _.each(activitySet.activityTypes, function (activityTypeFromSet) {
            activityTypeIDs.push(_.findKey(CRM.civicase.activityTypes, function (activitySet) {
              return activitySet.name === activityTypeFromSet.name;
            }));
          });
        }
      }

      // add activity types ids from the "Activity Type" filter
      if ($scope.filters['activity_type_id']) {
        activityTypeIDs = activityTypeIDs.concat($scope.filters['activity_type_id']);
      }

      if (activityTypeIDs.length) {
        params['activity_type_id'] = {IN: activityTypeIDs};
      }
    }
  }

  module.directive('civicaseActivityDetailsAffix', function ($timeout, $document, $rootScope) {
    return {
      link: civicaseActivityDetailsAffix
    };

    /**
     * Link function for civicaseActivityDetailsAffix
     *
     * @param {Object} scope
     * @param {Object} $element
     */
    function civicaseActivityDetailsAffix (scope, $element) {
      var $activityDetailsPanel, $filter, $feedListContainer, $tabs, $toolbarDrawer;

      (function init () {
        affixActivityDetailsPanel();
        $rootScope.$on('civicase::case-search::dropdown-toggle', resetAffix);
      }());

      /**
       * Sets Activity Details Panel affix offsets
       */
      function affixActivityDetailsPanel () {
        $timeout(function () {
          $activityDetailsPanel = $element.find('.panel');
          $filter = $('.civicase__activity-filter');
          $feedListContainer = $('.civicase__activity-feed__list-container');
          $tabs = $('.civicase__dashboard').length > 0 ? $('.civicase__dashboard__tab-container ul.nav') : $('.civicase__case-body_tab');
          $toolbarDrawer = $('#toolbar');

          $activityDetailsPanel.affix({
            offset: {
              top: $element.find('.panel').offset().top - ($toolbarDrawer.height() + $tabs.height() + $filter.height()),
              bottom: $($document).height() - ($feedListContainer.offset().top + $feedListContainer.height())
            }
          }).on('affixed.bs.affix', function () {
            $activityDetailsPanel
              .css('top', ($toolbarDrawer.height() + $tabs.height() + $filter.height()))
              .css('padding-top', 32);
          }).on('affixed-top.bs.affix', function () {
            $activityDetailsPanel
              .css('top', 'auto')
              .css('padding-top', 0);
          });
        });
      }

      /**
       * Resets Activity Details Panel affix offsets
       */
      function resetAffix () {
        $timeout(function () {
          if ($activityDetailsPanel.data('bs.affix')) {
            $activityDetailsPanel.data('bs.affix').options.offset.top = $activityDetailsPanel.offset().top - ($toolbarDrawer.height() + $tabs.height() + $filter.height());
            $activityDetailsPanel.data('bs.affix').options.offset.bottom = $($document).height() - ($feedListContainer.offset().top + $feedListContainer.height());
          }
        });
      }
    }
  });
})(angular, CRM.$, CRM._);
