(function (angular, $, _) {
  var module = angular.module('civicase');

  module.directive('civicaseActivityFilters', function ($rootScope, $timeout, ts,
    ActivityCategory, ActivityStatus, ActivityType, CustomActivityField,
    CaseTypeCategory, CaseTypeCategoryTranslationService, UrlParameters) {
    return {
      restrict: 'A',
      scope: {
        params: '=feedParams',
        showCheckboxes: '=?',
        bulkAllowed: '=',
        caseTimelines: '=',
        displayedCount: '=',
        totalCount: '=',
        filters: '=civicaseActivityFilters',
        displayOptions: '=displayOptions',
        selectedActivities: '=',
        isSelectAll: '=',
        canSelectCaseTypeCategory: '='
      },
      replace: true,
      templateUrl: '~/civicase/activity/filters/directives/activity-filters.directive.html',
      link: activityFiltersLink,
      transclude: true
    };

    /**
     * Link function for civicaseActivityFilters
     *
     * @param {object} $scope scope
     * @param {object} element element
     */
    function activityFiltersLink ($scope, element) {
      $scope.combinedFilterParams = {};
      $scope.activityCategories = prepareActivityCategories();
      $scope.availableFilters = prepareAvailableFilters();
      $scope.caseTypeCategories = CaseTypeCategory.getCategoriesWithAccessToActivity();
      // Default exposed filters
      $scope.exposedFilters = {
        activity_type_id: true,
        status_id: true,
        assignee_contact_id: true,
        tag_id: true,
        text: true
      };
      $scope.showIncludeCasesOption = showIncludeCasesOption;
      $scope.getShowCaseActivitiesLabel = getShowCaseActivitiesLabel;

      (function init () {
        // Get the current case type category from URL and restore its translations
        // This ensures the activity filters show the correct context-specific text
        var urlParams = UrlParameters.parse(window.location.href);
        var currentCaseTypeCategory = urlParams.case_type_category || 'Cases';

        // Find the category and restore its translations
        var currentCategory = _.find($scope.caseTypeCategories, function (category) {
          return category.name === currentCaseTypeCategory;
        });

        if (currentCategory) {
          CaseTypeCategoryTranslationService.restoreTranslation(currentCategory.value);
          // Cache the correct label after restoring translations
          $scope.showCaseActivitiesLabel = ts('Show case activities');
        } else {
          // Fallback if category not found
          $scope.showCaseActivitiesLabel = ts('Show case activities');
        }

        if ($scope.canSelectCaseTypeCategory) {
          $scope.filters.case_type_category = $scope.caseTypeCategories[0].name;
        }

        $scope.$on('civicaseActivityFeed.query', feedQueryListener);
        // Ensure set filters are also exposed
        _.each($scope.filters, function (filter, key) {
          $scope.exposedFilters[key] = true;
        });
      }());

      /**
       * Returns the correct label for "Show case activities" that won't be affected
       * by word replacements from other case category instances
       *
       * @returns {string} The correct label text
       */
      function getShowCaseActivitiesLabel () {
        return $scope.showCaseActivitiesLabel;
      }

      /**
       * Exposes the selected filter in the UI
       *
       * @param {object} field field
       * @param {object} $event event
       */
      $scope.exposeFilter = function (field, $event) {
        var shown = !$scope.exposedFilters[field.name];
        if (shown) {
          // Focus search element when selecting
          $timeout(function () {
            var $span = $('[data-activity-filter=' + field.name + ']', element);

            if ($('[crm-entityref], [crm-ui-select]', $span).length) {
              $('[crm-entityref], [crm-ui-select]', $span).select2('open');
            } else {
              $('input:first', $span).focus();
            }
          }, 50);
        } else {
          // Keep menu open when deselecting
          $event.stopPropagation();
          delete $scope.filters[field.name];
        }
      };

      /**
       * Checks if any filter has been applied
       *
       * @returns {boolean} if it has filters
       */
      $scope.hasFilters = function () {
        var result = false;

        _.each($scope.filters, function (value) {
          if (!_.isEmpty(value)) result = true;
        });

        return result;
      };

      /**
       * Clears all the filters
       */
      $scope.clearFilters = function () {
        _.each(_.keys($scope.filters), function (key) {
          delete $scope.filters[key];
        });
      };

      /**
       * Toogle More filters visibility
       */
      $scope.toggleMoreFilters = function () {
        $scope.filters['@moreFilters'] = !$scope.filters['@moreFilters'];

        $rootScope.$broadcast('civicase::activity-filters::more-filters-toggled');
      };

      /**
       * Subscribe listener for civicaseActivityFeed.query
       *
       * @param {object} event event
       * @param {object} feedQueryParams params
       */
      function feedQueryListener (event, feedQueryParams) {
        $scope.combinedFilterParams = angular.extend({}, feedQueryParams.apiParams, feedQueryParams.filters);
        delete $scope.combinedFilterParams['api.Activity.getactionlinks'];
      }

      /**
       * @returns {boolean} returns true if the include cases option should be visible
       */
      function showIncludeCasesOption () {
        var isPermissionAvailableToSeeCasesActivities =
          (CRM.checkPerm('access my cases and activities') ||
          CRM.checkPerm('access all cases and activities'));

        return isPermissionAvailableToSeeCasesActivities &&
          !!(!$scope.params.case_id &&
          $scope.params.filters.$contact_id);
      }

      /**
       * Prepare Activity Filters
       *
       * @returns {Array} filters
       */
      function prepareAvailableFilters () {
        var availableFilters = [
          {
            name: 'activity_type_id',
            label: ts('Activity type'),
            html_type: 'Select',
            options: _.map(ActivityType.getAll(), mapSelectOptions)
          },
          {
            name: 'status_id',
            label: ts('Status'),
            html_type: 'Select',
            options: _.map(ActivityStatus.getAll(), mapSelectOptions)
          },
          {
            name: 'target_contact_id',
            label: ts('With'),
            html_type: 'Autocomplete-Select',
            entity: 'Contact'
          },
          {
            name: 'assignee_contact_id',
            label: ts('Assigned to'),
            html_type: 'Autocomplete-Select',
            entity: 'Contact'
          },
          {
            name: 'tag_id',
            label: ts('Tagged'),
            html_type: 'Autocomplete-Select',
            entity: 'Tag',
            api_params: { used_for: { LIKE: '%civicrm_activity%' }, is_tagset: 0 }
          },
          {
            name: 'text',
            label: ts('Contains text'),
            html_type: 'Text'
          },
          {
            name: 'activity_date_time',
            label: ts('Activity date'),
            html_type: 'Select Date'
          }
        ];

        if (_.includes(CRM.config.enableComponents, 'CiviCampaign')) {
          availableFilters.push({
            name: 'campaign_id', label: ts('Campaign'), html_type: 'Autocomplete-Select', entity: 'Campaign'
          });
        }
        if (CRM.checkPerm('administer CiviCRM')) {
          availableFilters.push({
            name: 'is_deleted',
            label: ts('Deleted Activities'),
            html_type: 'Select',
            options: [{ id: 1, text: ts('Deleted') }, { id: 0, text: ts('Normal') }]
          },
          {
            name: 'is_test',
            label: ts('Test Activities'),
            html_type: 'Select',
            options: [{ id: 1, text: ts('Test') }, { id: 0, text: ts('Normal') }]
          });
        }

        availableFilters = availableFilters.concat(CustomActivityField.getAll());

        return availableFilters;
      }

      /**
       * Prepare Activity Categories
       *
       * @returns {Array} categories
       */
      function prepareActivityCategories () {
        return _.map(ActivityCategory.getAll(), function (category, key) {
          category.id = key;
          category.text = category.label;

          return category;
        });
      }

      /**
       * Maps Options to be used in the dropdown
       *
       * @param {object} option option
       * @returns {object} options
       */
      function mapSelectOptions (option) {
        return {
          id: option.value,
          text: option.label,
          color: option.color,
          icon: option.icon
        };
      }
    }
  });
})(angular, CRM.$, CRM._);
