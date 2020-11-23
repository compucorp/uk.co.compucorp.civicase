/* eslint-env jasmine */

((_) => {
  describe('case management workflow', () => {
    let $q, $rootScope, civicaseCrmApiMock, CaseTypesMockData,
      CasemanagementWorkflow;

    beforeEach(module('workflow', 'civicase.data', ($provide) => {
      civicaseCrmApiMock = jasmine.createSpy('civicaseCrmApi');

      $provide.value('civicaseCrmApi', civicaseCrmApiMock);
    }));

    beforeEach(inject((_$q_, _$rootScope_, _CasemanagementWorkflow_,
      _CaseTypesMockData_) => {
      $q = _$q_;
      $rootScope = _$rootScope_;
      CasemanagementWorkflow = _CasemanagementWorkflow_;
      CaseTypesMockData = _CaseTypesMockData_;
    }));

    describe('when getting list of workflow', () => {
      var results;

      beforeEach(() => {
        civicaseCrmApiMock.and.returnValue($q.resolve({
          values: CaseTypesMockData.getSequential()
        }));

        CasemanagementWorkflow.getWorkflowsList('some_case_type_category')
          .then(function (data) {
            results = data;
          });
        $rootScope.$digest();
      });

      it('fetches the workflows for the case management instance', () => {
        expect(civicaseCrmApiMock).toHaveBeenCalledWith('CaseType', 'get', {
          sequential: 1,
          case_type_category: 'some_case_type_category',
          options: { limit: 0 }
        });
      });

      it('displays the list of fetched workflows', () => {
        expect(results).toEqual(CaseTypesMockData.getSequential());
      });
    });

    describe('when duplicating a workflow', () => {
      var workflow;

      beforeEach(() => {
        workflow = CaseTypesMockData.getSequential(0);
        CasemanagementWorkflow.createDuplicate(workflow);
      });

      it('creates a duplicate workflow', () => {
        expect(civicaseCrmApiMock).toHaveBeenCalledWith([
          ['CaseType', 'create', _.extend({}, workflow, { id: null })]
        ]);
      });
    });
  });
})(CRM._);
