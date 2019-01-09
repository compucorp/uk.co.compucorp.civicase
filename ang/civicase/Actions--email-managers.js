(function (angular, $, _) {
  var module = angular.module('civicase');

  module.service('EmailManagersCaseAction', EmailManagersCaseAction);

  function EmailManagersCaseAction () {
    /**
     * Get path object to email managers
     *
     * @param {Array} cases
     * @return {Object}
     */
    this.getPath = function (cases) {
      var managers = [];

      _.each(cases, function (item) {
        if (item.manager) {
          managers.push(item.manager.contact_id);
        }
      });

      var popupPath = {
        path: 'civicrm/activity/email/add',
        query: {
          action: 'add',
          reset: 1,
          cid: _.uniq(managers).join(',')
        }
      };

      if (cases.length === 1) {
        popupPath.query.caseid = cases[0].id;
      }

      return popupPath;
    };
  }
})(angular, CRM.$, CRM._);
