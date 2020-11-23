/* eslint-env jasmine */

((_) => {
  describe('workflow list', () => {
    let $q, $controller, $rootScope, $scope, CaseTypesMockData,
      civicaseCrmApiMock, WorkflowListActionItems, CasemanagementWorkflow,
      WorkflowListColumns;

    beforeEach(module('workflow', 'civicase.data', ($provide) => {
      civicaseCrmApiMock = jasmine.createSpy('civicaseCrmApi');

      $provide.value('civicaseCrmApi', civicaseCrmApiMock);
    }));

    beforeEach(inject((_$q_, _$controller_, _$rootScope_, _CaseTypesMockData_,
      _WorkflowListActionItems_, _CasemanagementWorkflow_,
      _WorkflowListColumns_) => {
      $q = _$q_;
      $controller = _$controller_;
      $rootScope = _$rootScope_;
      WorkflowListActionItems = _WorkflowListActionItems_;
      WorkflowListColumns = _WorkflowListColumns_;
      CaseTypesMockData = _CaseTypesMockData_;
      CasemanagementWorkflow = _CasemanagementWorkflow_;

      spyOn(CasemanagementWorkflow, 'getWorkflowsList');
    }));

    describe('basic tests', () => {
      beforeEach(() => {
        CasemanagementWorkflow.getWorkflowsList.and.returnValue($q.resolve(
          CaseTypesMockData.getSequential()
        ));
        initController();
      });

      it('hides the empty message before case types are loaded', () => {
        expect($scope.isLoading).toBe(true);
      });

      describe('after case types are loaded', () => {
        beforeEach(() => {
          $scope.$digest();
        });

        it('shows the results after case types is loaded', () => {
          expect($scope.isLoading).toBe(false);
        });

        it('displays the list of fetched workflows', () => {
          expect($scope.workflows).toEqual(CaseTypesMockData.getSequential());
        });

        it('displays the action items dropdown', () => {
          expect($scope.actionItems).toEqual(WorkflowListActionItems);
        });

        describe('columns', () => {
          var expectedColumns;

          beforeEach(function () {
            expectedColumns = _.map(WorkflowListColumns, function (column) {
              column = _.extend({}, column);
              column.isVisible = true;

              return column;
            });
          });

          it('displays the columns', () => {
            expect($scope.tableColumns).toEqual(expectedColumns);
          });
        });
      });
    });

    describe('when list refresh event is fired', () => {
      beforeEach(() => {
        CasemanagementWorkflow.getWorkflowsList.and.returnValue($q.resolve(
          CaseTypesMockData.getSequential()
        ));
        initController();
        $scope.$digest();
        $scope.workflows = [];
        $rootScope.$broadcast('workflow::list::refresh');
        $scope.$digest();
      });

      it('fetches the case types for the current case type category', () => {
        expect(CasemanagementWorkflow.getWorkflowsList).toHaveBeenCalled();
      });

      it('refreshes the workflows list', () => {
        expect($scope.workflows).toEqual(CaseTypesMockData.getSequential());
      });
    });

    /**
     * Initializes the contact case tab case details controller.
     */
    function initController () {
      $scope = $rootScope.$new();
      $scope.caseTypeCategory = 'Cases';

      $controller('workflowListController', { $scope: $scope });
    }
  });
})(CRM._);
