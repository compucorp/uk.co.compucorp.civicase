(function ($, _) {
  describe('civicaseMyActivities', () => {
    var $controller, $rootScope, $scope;

    beforeEach(module('civicase.data', 'civicase', 'civicase.templates'));

    beforeEach(inject((_$controller_, _$rootScope_) => {
      $controller = _$controller_;
      $rootScope = _$rootScope_;
      $scope = $rootScope.$new();
      $scope.filters = {};

      initController();
    }));

    describe('on init', () => {
      it('shows the case activites', () => {
        expect($scope.displayOptions).toEqual({ include_case: true });
      });

      it('shows the incomplete activites for current logged in user', () => {
        expect($scope.filters).toEqual({
          $contact_id: 203,
          '@involvingContact': 'myActivities',
          status_id: ['1', '4', '7', '9', '10']
        });
      });
    });

    /**
     * Initializes the my activity controller.
     */
    function initController () {
      $controller('CivicaseMyActivitiesController', {
        $scope: $scope
      });
    }
  });
})(CRM.$, CRM._);
