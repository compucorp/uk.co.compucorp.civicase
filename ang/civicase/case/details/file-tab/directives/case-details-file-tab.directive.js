(function (angular, $, _) {
  var module = angular.module('civicase');

  module.directive('civicaseCaseDetailsFileTab', function () {
    return {
      restrict: 'AE',
      templateUrl: '~/civicase/case/details/file-tab/directives/case-details-file-tab.directive.html',
      scope: {
        item: '=civicaseCaseDetailsFileTab',
        refresh: '=?refreshCallback'
      },
      controller: 'civicaseCaseDetailsFileTabController'
    };
  });

  module.controller('civicaseCaseDetailsFileTabController', civicaseCaseDetailsFileTabController);

  /**
   * Controller function for the directive
   *
   * @param {object} $scope controllers scope object
   * @param {object} BulkActions bulk actions service
   * @param {object} civicaseCrmApi service to call civicrm backend
   * @param {Function} formatActivity format activity service
   */
  function civicaseCaseDetailsFileTabController ($scope, BulkActions, civicaseCrmApi, formatActivity) {
    $scope.bulkAllowed = BulkActions.isAllowed();
    $scope.isSelectAll = false;
    $scope.isLoading = true;
    $scope.selectedActivities = [];
    $scope.totalCount = 0;
    $scope.fileFilterParams = {
      case_id: $scope.item.id,
      text: '',
      options: { xref: 0, limit: 0 },
      is_file: true
    };

    $scope.findActivityById = findActivityById;
    $scope.toggleSelected = toggleSelected;
    $scope.refresh = refresh;
    $scope.loadActivities = loadActivities;
    $scope.loadActivityDetails = loadActivityDetails;

    (function init () {
      loadActivities();

      $scope.$on('civicase::bulk-actions::bulk-selections', bulkSelectionsListener);
      $scope.$on('civicase::activity::updated', refresh);
    }());

    /**
     * Bulk Selection Event Listener
     * Performs different types of bulk action actions(select/deselect etc)
     * based on sent parameters
     *
     * @param {object} event event
     * @param {string} condition condition
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
      $scope.isSelectAll = false;
      $scope.selectedActivities = [];
    }

    /**
     * Find activity by given ID
     *
     * @param {Array} searchIn - array of activities to search into
     * @param {number/string} activityID activityID
     * @returns {object} activity
     */
    function findActivityById (searchIn, activityID) {
      return _.find(searchIn, { id: activityID });
    }

    /**
     * Load full activity details on demand
     * Called when user interacts with an activity that needs more data
     *
     * @param {object} activity - the activity to load details for
     * @returns {Promise} promise
     */
    function loadActivityDetails (activity) {
      if (activity.detailsLoaded || activity.loadingDetails) {
        return;
      }

      activity.loadingDetails = true;
      
      return civicaseCrmApi('Activity', 'getsingle', {
        id: activity.activity_id,
        return: ['subject', 'details', 'activity_type_id', 'status_id',
                 'source_contact_name', 'target_contact_name', 'assignee_contact_name',
                 'activity_date_time', 'is_star', 'original_id', 'tag_id.name',
                 'tag_id.description', 'tag_id.color', 'file_id', 'is_overdue', 'case_id']
      }).then(function (details) {
        // Merge the detailed data into the activity
        _.extend(activity, details);
        activity.detailsLoaded = true;
        delete activity.loadingDetails;
        formatActivity(activity);
      }).catch(function () {
        delete activity.loadingDetails;
      });
    }

    /**
     * Get List of Activities
     */
    function loadActivities () {
      $scope.isLoading = true;
      $scope.activities = [];
      civicaseCrmApi('Case', 'getfiles', $scope.fileFilterParams)
        .then(function (result) {
          // Handle response without xref - just get basic file info
          if (result.values) {
            $scope.activities = _.chain(result.values)
              .map(function (file) {
                // Transform file data to activity format
                return {
                  id: file.activity_id,
                  activity_id: file.activity_id,
                  subject: file.subject || file.description || 'File attachment',
                  activity_date_time: file.activity_date_time,
                  file_id: [file.id],
                  file_uri: file.uri,
                  file_mime_type: file.mime_type,
                  can_be_deleted: true,
                  // Mark that we need to load full details on demand
                  detailsLoaded: false
                };
              })
              .each(formatActivity)
              .sortBy('activity_date_time')
              .reverse()
              .value();
          } else if (result.xref) {
            // Fallback to original xref handling if available
            $scope.activities = _.chain(result.xref.activity)
              .each(formatActivity)
              .sortBy('activity_date_time')
              .reverse()
              .value();
          } else {
            $scope.activities = [];
          }

          $scope.totalCount = result.count || $scope.activities.length;
        })
        .finally(function () {
          $scope.isLoading = false;
        });
    }

    /**
     * Refreshes the UI state after updating the db from the api calls
     *
     * @param {Array} apiCalls list of api calls
     */
    function refresh (apiCalls) {
      if (!_.isArray(apiCalls)) apiCalls = [];

      civicaseCrmApi(apiCalls, true).then(loadActivities);
    }

    /**
     * Select All visible data.
     */
    function selectDisplayedActivities () {
      $scope.isSelectAll = false;
      var isCurrentActivityInSelectedCases;

      _.each($scope.activities, function (activity) {
        isCurrentActivityInSelectedCases = $scope.findActivityById($scope.selectedActivities, activity.id);

        if (!isCurrentActivityInSelectedCases) {
          $scope.selectedActivities.push($scope.findActivityById($scope.activities, activity.id));
        }
      });
    }

    /**
     * Select all Activity
     */
    function selectEveryActivity () {
      deselectAllActivities();
      $scope.isSelectAll = true;
    }

    /**
     * Toggle Bulk Actions checkbox of the given activity
     *
     * @param {object} activity activity
     */
    function toggleSelected (activity) {
      if ($scope.isSelectAll) {
        deselectAllActivities();
      }

      if (!$scope.findActivityById($scope.selectedActivities, activity.id)) {
        $scope.selectedActivities.push($scope.findActivityById($scope.activities, activity.id));
      } else {
        _.remove($scope.selectedActivities, { id: activity.id });
      }
    }
  }
})(angular, CRM.$, CRM._);
