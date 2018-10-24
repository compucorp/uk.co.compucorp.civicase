(function (angular, _) {
  var module = angular.module('civicase.data');

  module.service('caseHeadersMockData', function () {
    var caseHeadersMockData = [
      {
        'name': 'next_activity',
        'label': 'Next Activity',
        'sort': 'next_activity',
        'display_type': 'activity_card'
      },
      {
        'name': 'subject',
        'label': 'Subject',
        'sort': 'subject',
        'display_type': 'default'
      },
      {
        'name': 'status',
        'label': 'Status',
        'sort': 'status_id.label',
        'display_type': 'status_badge'
      },
      {
        'name': 'case_type',
        'label': 'Type',
        'sort': 'case_type_id.title',
        'display_type': 'default'
      },
      {
        'name': 'manager',
        'label': 'Case Manager',
        'sort': 'case_manager.sort_name',
        'display_type': 'contact_reference'
      },
      {
        'name': 'start_date',
        'label': 'Start Date',
        'sort': 'start_date',
        'display_type': 'date'
      },
      {
        'name': 'modified_date',
        'label': 'Last Updated',
        'sort': 'modified_date',
        'display_type': 'date'
      },
      {
        'name': 'myRole',
        'label': 'My Role',
        'sort': 'my_role.label_b_a',
        'display_type': 'multiple_values'
      }
    ];

    return {
      /**
       * Returns a list of mocked case headers
       *
       * @return {Array} each array contains an object with the case header data.
       */
      get: function () {
        return _.cloneDeep(caseHeadersMockData);
      }
    };
  });
})(angular, CRM._);
