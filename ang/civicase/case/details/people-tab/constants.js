(function (angular, configuration) {
  var module = angular.module('civicase');

  // Using providers in order to be able to use the `ts` service
  module.config(function ($provide, tsProvider) {
    var ts = tsProvider.$get();

    $provide.constant(
      'CONTACT_CANT_HAVE_ROLE_MESSAGE',
      ts('Case clients cannot be selected for a case role. Please select another contact.')
    );
    $provide.constant(
      'CONTACT_NOT_SELECTED_MESSAGE',
      ts('Please select a contact.')
    );
    $provide.constant(
      'RELATIONSHIP_END_DATE_MESSAGE',
      ts('End date cannot be before start date of the relationship.')
    );
    $provide.constant(
      'RELATIONSHIP_REASSIGNMENT_DATE_MESSAGE',
      ts('Reassignment date cannot be before start date of the relationship.')
    );
  });
})(angular, CRM['civicase-base']);
