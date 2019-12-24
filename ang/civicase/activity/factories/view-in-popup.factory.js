(function (angular, $, _, CRM) {
  var module = angular.module('civicase');

  module.factory('viewInPopup', function (ActivityType) {
    /**
     * View given activity in a popup
     *
     * @param {object} $event event
     * @param {*} activity activity to be viewed
     * @returns {object} jQuery object
     */
    function viewInPopup ($event, activity) {
      if ($event && $($event.target).is('a, a *, input, button, button *')) {
        return;
      }

      return CRM.loadForm(CRM.url(getFormUrl(activity), getFormParams(activity)));
    }

    /**
     * Get Form url for the given activity
     *
     * @param {object} activity activity for which url needs to be generated
     * @returns {string} form url
     */
    function getFormUrl (activity) {
      var activityFormUrl = 'civicrm/activity';

      if (activity.case_id) {
        activityFormUrl = 'civicrm/case/activity';
      }

      if (activity.type === 'Applicant Review') {
        activityFormUrl = 'civicrm/awardreview';
      }

      if (checkIfDraftEmailOrPDFActivity(activity)) {
        activityFormUrl = 'civicrm/activity/email/add';
      }

      return activityFormUrl;
    }

    /**
     * Get Form parameters for the given activity
     *
     * @param {object} activity activity for which parameters needs to be generated
     * @returns {object} form parameters
     */
    function getFormParams (activity) {
      var activityFormParams = {
        action: 'update',
        id: activity.id,
        reset: 1
      };

      if (activity.case_id) {
        activityFormParams.caseid = activity.case_id;
      }

      if (checkIfDraftEmailOrPDFActivity(activity)) {
        activityFormParams = {
          action: 'add',
          reset: '1',
          caseId: activity.case_id,
          context: 'standalone',
          draft_id: activity.id
        };
      }

      return activityFormParams;
    }

    /**
     * Checks if the given activity is in draft state and is of email of pdf type
     *
     * @param {object} activity activty object
     * @returns {boolean} if email or pdf type and if in draft state
     */
    function checkIfDraftEmailOrPDFActivity (activity) {
      var activityTypeName = ActivityType.findById(activity.activity_type_id).name;

      var isDraftEmailOrPdfTypeActivity =
        (activityTypeName === 'Email' || activityTypeName === 'Print PDF Letter') &&
        activity.status_name === 'Draft';

      return isDraftEmailOrPdfTypeActivity;
    }

    return viewInPopup;
  });
})(angular, CRM.$, CRM._, CRM);
