/* eslint-env jasmine */

((_) => {
  describe('Contact Case Tab', () => {
    var $controller, $rootScope, $scope, crmApi, mockContactId, mockContactService;

    beforeEach(module('civicase', ($provide) => {
      mockContactService = jasmine.createSpyObj('Contact', ['getContactIDFromUrl']);

      $provide.value('Contact', mockContactService);
    }));

    beforeEach(inject((_$controller_, _$rootScope_, _crmApi_) => {
      $controller = _$controller_;
      $rootScope = _$rootScope_;
      crmApi = _crmApi_;
    }));

    beforeEach(() => {
      mockContactId = _.uniqueId();

      mockContactService.getContactIDFromUrl.and.returnValue(mockContactId);
      initController();
    });

    describe('on init', () => {
      it('stores the contact id extracted from the URL', () => {
        expect($scope.contactId).toBe(mockContactId);
      });
    });

    describe('when loading cases', () => {
      it('requests non deleted opened cases for the given contact', () => {
        expect(crmApi.calls.allArgs()).toContain(jasmine.arrayContaining([
          jasmine.objectContaining({
            cases: ['Case', 'getcaselist', jasmine.objectContaining({
              'status_id.grouping': 'Opened',
              contact_id: mockContactId,
              is_deleted: 0
            })]
          })
        ]));
      });

      it('requests non deleted closed cases for the given contact', () => {
        expect(crmApi.calls.allArgs()).toContain(jasmine.arrayContaining([
          jasmine.objectContaining({
            cases: ['Case', 'getcaselist', jasmine.objectContaining({
              'status_id.grouping': 'Closed',
              contact_id: mockContactId,
              is_deleted: 0
            })]
          })
        ]));
      });

      it('requests non deleted cases where the contact is a manager', () => {
        expect(crmApi.calls.allArgs()).toContain(jasmine.arrayContaining([
          jasmine.objectContaining({
            cases: ['Case', 'getcaselist', jasmine.objectContaining({
              case_manager: mockContactId,
              is_deleted: 0
            })]
          })
        ]));
      });
    });

    /**
     * Initializes the contact case tab controller.
     */
    function initController () {
      $scope = $rootScope.$new();

      $controller('CivicaseContactCaseTabController', { $scope: $scope });
    }
  });
})(CRM._);
