(function (angular) {
  var module = angular.module('civicase');

  module.service('UpdateActivityForm', UpdateActivityForm);

  /**
   * Update Activity Form service.
   *
   * @param {Function} civicaseCrmUrl crm URL service.
   */
  function UpdateActivityForm (civicaseCrmUrl) {
    this.canChangeStatus = true;
    this.canHandleActivity = canHandleActivity;
    this.getActivityFormUrl = getActivityFormUrl;

    /**
     * Only handles activity forms that will be updated.
     * It supports both stand-alone activities and case activities.
     *
     * @param {object} activity an activity object.
     * @param {object} [options] form options.
     * @returns {boolean} true when it can handle the activity and form options.
     */
    function canHandleActivity (activity, options) {
      return (options && options.action === 'update') || false;
    }

    /**
     * @param {object} activity an activity object.
     * @returns {string} the URL for the activity form that will be displayed.
     */
    function getActivityFormUrl (activity) {
      var urlPath = 'civicrm/activity';
      var urlParams = {
        action: 'update',
        id: activity.id,
        reset: 1
      };

      if (activity.case_id) {
        urlPath = 'civicrm/activity/add';
        urlParams.caseid = activity.case_id;
        urlParams.context = 'case';
      }

      return civicaseCrmUrl(urlPath, urlParams);
    }
  }
})(angular);
