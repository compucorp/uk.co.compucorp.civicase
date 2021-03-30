(function (angular, CRM) {
  var module = angular.module('civicase.data');

  CRM['civicase-base'].activityStatuses = {
    1: {
      value: '1',
      label: 'Scheduled',
      color: '#42afcb',
      name: 'Scheduled',
      grouping: 'none,task,file,communication,milestone,system',
      is_active: '1',
      weight: '1',
      filter: '0'
    },
    2: {
      value: '2',
      label: 'Completed',
      color: '#8ec68a',
      name: 'Completed',
      grouping: 'none,task,file,communication,milestone,alert,system',
      is_active: '1',
      weight: '2',
      filter: '1'
    },
    3: {
      value: '3',
      label: 'Cancelled',
      name: 'Cancelled',
      grouping: 'none,communication,milestone,alert',
      is_active: '1',
      weight: '3',
      filter: '2'
    },
    4: {
      value: '4',
      label: 'Left Message',
      color: '#eca67f',
      name: 'Left Message',
      grouping: 'none,communication,milestone',
      is_active: '1',
      weight: '4',
      filter: '0'
    },
    5: {
      value: '5',
      label: 'Unreachable',
      name: 'Unreachable',
      grouping: 'none,communication,milestone',
      is_active: '1',
      weight: '5',
      filter: '2'
    },
    6: {
      value: '6',
      label: 'Not Required',
      name: 'Not Required',
      grouping: 'none,task,milestone',
      is_active: '1',
      weight: '6',
      filter: '2'
    },
    7: {
      value: '7',
      label: 'Available',
      color: '#5bc0de',
      name: 'Available',
      grouping: 'none,milestone',
      is_active: '1',
      weight: '7',
      filter: '0'
    },
    8: {
      value: '8',
      label: 'No-show',
      name: 'No_show',
      grouping: 'none,milestone',
      is_active: '1',
      weight: '8',
      filter: '2'
    },
    9: {
      value: '9',
      label: 'Unread',
      color: '#d9534f',
      name: 'Unread',
      grouping: 'communication',
      is_active: '1',
      weight: '9',
      filter: '0'
    },
    10: {
      value: '10',
      label: 'Draft',
      color: '#c2cfd8',
      name: 'Draft',
      grouping: 'communication',
      is_active: '1',
      weight: '10',
      filter: '0'
    }
  };

  module.constant('ActivityStatusesData', {
    values: CRM['civicase-base'].activityStatuses
  });
})(angular, CRM);
