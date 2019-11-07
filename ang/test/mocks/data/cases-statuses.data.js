(function () {
  var module = angular.module('civicase.data');
  CRM['civicase-base'].caseStatuses = {
    1: {
      value: '1',
      label: 'Ongoing',
      color: '#42afcb',
      name: 'Open',
      grouping: 'Opened',
      weight: '1'
    },
    2: {
      value: '2',
      label: 'Resolved',
      color: '#4d5663',
      name: 'Closed',
      grouping: 'Closed',
      weight: '2'
    },
    3: {
      value: '3',
      label: 'Urgent',
      color: '#e6807f',
      name: 'Urgent',
      grouping: 'Opened',
      weight: '3'
    }
  };

  module.constant('CaseStatuses', {
    values: CRM['civicase-base'].caseStatuses
  });
}());
