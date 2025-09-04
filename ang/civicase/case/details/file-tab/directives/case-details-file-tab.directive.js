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
      options: { xref: 0, limit: 0 }, // Performance optimization - no xref
      is_file: true
    };

    $scope.findActivityById = findActivityById;
    $scope.toggleSelected = toggleSelected;
    $scope.refresh = refresh;
    $scope.loadActivities = loadActivities;

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
     * Get List of Activities
     */
    function loadActivities () {
      $scope.isLoading = true;
      $scope.activities = [];
      civicaseCrmApi('Case', 'getfiles', $scope.fileFilterParams)
        .then(function (result) {
          // Handle response without xref - just get basic file info
          if (result.values && result.values.length > 0) {
            // First, group files by activity_id to handle activities with multiple files
            var filesByActivity = _.groupBy(result.values, 'activity_id');

            $scope.activities = _.chain(filesByActivity)
              .filter(function (files, activityId) {
                // Filter out activities with no files
                return files && files.length > 0;
              })
              .map(function (files, activityId) {
                // Take the first file's data as the base for the activity
                var firstFile = files[0];

                // Parse target contacts from GROUP_CONCAT result (same for all files of this activity)
                var targetContactIds = firstFile.target_contact_ids ? firstFile.target_contact_ids.split(',') : [];
                var targetContactNames = firstFile.target_contact_names ? firstFile.target_contact_names.split(',') : [];
                var targetContacts = {};

                // Build target_contact_name object in CiviCRM format
                targetContactIds.forEach(function(id, index) {
                  if (id && targetContactNames[index]) {
                    targetContacts[id] = targetContactNames[index];
                  }
                });

                // Parse assignee contacts from GROUP_CONCAT result
                var assigneeContactIds = firstFile.assignee_contact_ids ? firstFile.assignee_contact_ids.split(',') : [];
                var assigneeContactNames = firstFile.assignee_contact_names ? firstFile.assignee_contact_names.split(',') : [];
                var assigneeContacts = {};

                // Build assignee_contact_name object in CiviCRM format
                assigneeContactIds.forEach(function(id, index) {
                  if (id && assigneeContactNames[index]) {
                    assigneeContacts[id] = assigneeContactNames[index];
                  }
                });

                // Collect all file IDs, URIs, and mime types for this activity
                var fileIds = [];
                var fileUris = [];
                var fileMimeTypes = [];
                files.forEach(function(file) {
                  fileIds.push(file.id);
                  fileUris.push(file.uri);
                  fileMimeTypes.push(file.mime_type);
                });

                // Parse tags from GROUP_CONCAT result (format: id|name|color|description)
                var tags = {};
                if (firstFile.tag_data) {
                  var tagEntries = firstFile.tag_data.split(',');
                  tagEntries.forEach(function(tagEntry) {
                    var parts = tagEntry.split('|');
                    if (parts.length >= 4 && parts[0] && parts[1]) {
                      var tagId = parts[0];
                      var tagName = parts[1];
                      var tagColor = parts[2] !== '' ? parts[2] : null;
                      var tagDescription = parts[3] !== '' ? parts[3] : null;
                      
                      tags[tagId] = {
                        'tag_id.name': tagName,
                        'tag_id.color': tagColor,
                        'tag_id.description': tagDescription
                      };
                    }
                  });
                }

                // Create activity object with contact information
                return {
                  id: firstFile.activity_id,
                  activity_id: firstFile.activity_id,
                  activity_type_id: firstFile.activity_type_id, // Use actual activity type from API
                  status_id: firstFile.status_id, // Use actual status from API
                  case_id: $scope.item.id, // Include case_id for proper form URL
                  subject: firstFile.subject || firstFile.description || 'File attachment',
                  activity_date_time: firstFile.activity_date_time,
                  file_id: fileIds,  // Array of ALL file IDs for this activity
                  file_uri: fileUris,  // Always array for consistent API
                  file_mime_type: fileMimeTypes,  // Always array for consistent API
                  can_be_deleted: true,
                  is_star: firstFile.is_star || '0', // Add star field, default to '0' if not set
                  tag_id: Object.keys(tags).length > 0 ? tags : null, // Add tags object (null if no tags)
                  // Add contact fields for To/From display
                  // For source contact, use the format expected by contact-card directive
                  source_contact_id: firstFile.source_contact_id || null,  // Pass as string/number, not array
                  source_contact_name: firstFile.source_contact_name && firstFile.source_contact_id ? {[firstFile.source_contact_id]: firstFile.source_contact_name} : null,
                  target_contact_id: targetContactIds,
                  target_contact_name: Object.keys(targetContacts).length > 0 ? targetContacts : null,
                  // Add assignee contact fields for avatar display
                  assignee_contact_id: assigneeContactIds,
                  assignee_contact_name: Object.keys(assigneeContacts).length > 0 ? assigneeContacts : null,
                  total_assignee_contacts: assigneeContactIds.length
                };
              })
              .each(function(activity) {
                formatActivity(activity, $scope.item.id); // Pass case_id to formatActivity
              })
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
