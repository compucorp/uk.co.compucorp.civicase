(function (angular, CRM) {
  var module = angular.module('civicase.data');

  CRM['civicase-base'].activityStatuses = {
    1: {
      value: '1',
      label: 'Scheduled',
      filter: '0',
      color: '#42afcb',
      name: 'Scheduled',
      grouping: 'none,task,file,communication,milestone,system',
      is_active: '1'
    },
    2: {
      value: '2',
      label: 'Completed',
      filter: '1',
      color: '#8ec68a',
      name: 'Completed',
      grouping: 'none,task,file,communication,milestone,alert,system',
      is_active: '1'
    },
    3: {
      value: '3',
      label: 'Cancelled',
      filter: '1',
      name: 'Cancelled',
      grouping: 'none,communication,milestone,alert',
      is_active: '1'
    },
    4: {
      value: '4',
      label: 'Left Message',
      filter: '0',
      color: '#eca67f',
      name: 'Left Message',
      grouping: 'none,communication,milestone',
      is_active: '1'
    },
    5: {
      value: '5',
      label: 'Unreachable',
      filter: '2',
      name: 'Unreachable',
      grouping: 'none,communication,milestone',
      is_active: '1'
    },
    6: {
      value: '6',
      label: 'Not Required',
      filter: '1',
      name: 'Not Required',
      grouping: 'none,task,milestone',
      is_active: '1'
    },
    7: {
      value: '7',
      label: 'Available',
      filter: '0',
      color: '#5bc0de',
      name: 'Available',
      grouping: 'none,milestone',
      is_active: '1'
    },
    8: {
      value: '8',
      label: 'No-show',
      filter: '2',
      name: 'No_show',
      grouping: 'none,milestone',
      is_active: '1'
    },
    9: {
      value: '9',
      label: 'Unread',
      filter: '0',
      color: '#d9534f',
      name: 'Unread',
      grouping: 'communication',
      is_active: '1'
    },
    10: {
      value: '10',
      label: 'Draft',
      filter: '0',
      color: '#c2cfd8',
      name: 'Draft',
      grouping: 'communication',
      is_active: '1'
    }
  };

  module.constant('ActivityStatusesData', {
    values: CRM['civicase-base'].activityStatuses
  });
})(angular, CRM);
