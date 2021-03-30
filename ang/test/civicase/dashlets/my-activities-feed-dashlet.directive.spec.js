((_) => {
  describe('civicaseMyActivitiesFeedDashlet', () => {
    let $controller, $rootScope, $scope, Contact;

    beforeEach(module('civicase', 'civicase.data'));

    beforeEach(inject((_$controller_, _$rootScope_, _Contact_) => {
      $controller = _$controller_;
      $rootScope = _$rootScope_;
      Contact = _Contact_;

      $scope = $rootScope.$new();
    }));

    describe('activity filters', () => {
      const INCOMPLETE_ACTIVITY_STATUSES = [
        { value: '1', label: 'Scheduled' },
        { value: '4', label: 'Left Message' },
        { value: '7', label: 'Available' },
        { value: '9', label: 'Unread' },
        { value: '10', label: 'Draft' }
      ];

      beforeEach(() => {
        initController();
      });

      it('filters activities by the current logged in user ID', () => {
        expect($scope.filters.$contact_id).toEqual(Contact.getCurrentContactID());
      });

      it('filters activities assigned to the current logged in user', () => {
        expect($scope.filters['@involvingContact']).toEqual('myActivities');
      });

      it('filters activities that have not been completed', () => {
        expect($scope.filters.status_id)
          .toEqual(_.map(INCOMPLETE_ACTIVITY_STATUSES, 'value'));
      });
    });

    /**
     * Initializes the directive's controller.
     */
    function initController () {
      $controller('civicaseMyActivitiesFeedDashletController', {
        $scope: $scope
      });
    }
  });
})(CRM._);
