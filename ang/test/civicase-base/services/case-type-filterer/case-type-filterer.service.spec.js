/* eslint-env jasmine */

((_) => {
  describe('CaseTypeFilterer service', () => {
    let allCaseTypeCategories, allCaseTypes, CaseTypeFilterer,
      CaseTypesMockDataProvider, expectedCaseTypes, returnedCaseTypes;

    beforeEach(module('civicase-base', 'civicase.data', (_CaseTypesMockDataProvider_) => {
      CaseTypesMockDataProvider = _CaseTypesMockDataProvider_;

      CaseTypesMockDataProvider.add({
        title: 'inactive case type',
        is_active: '0'
      });
    }));

    beforeEach(inject((_CaseType_, _CaseTypeCategory_, _CaseTypeFilterer_) => {
      allCaseTypeCategories = _CaseTypeCategory_.getAll();
      allCaseTypes = _CaseType_.getAll({ includeInactive: true });
      CaseTypeFilterer = _CaseTypeFilterer_;
    }));

    afterEach(() => {
      CaseTypesMockDataProvider.reset();
    });
    describe('when filtering by case type id', () => {
      beforeEach(() => {
        const sampleCaseType = _.chain(allCaseTypes)
          .filter({ is_active: '1' })
          .sample()
          .value();

        expectedCaseTypes = [
          sampleCaseType
        ];

        returnedCaseTypes = CaseTypeFilterer.filter({
          id: sampleCaseType.id
        });
      });

      it('returns a list of case types that only includes the requested case type', () => {
        expect(returnedCaseTypes).toEqual(expectedCaseTypes);
      });
    });

    describe('when filtering by a list of case type ids', () => {
      beforeEach(() => {
        expectedCaseTypes = _.chain(allCaseTypes)
          .filter({ is_active: '1' })
          .sample(2)
          .value();
        const expectedCaseTypeIds = _.map(expectedCaseTypes, 'id');

        returnedCaseTypes = CaseTypeFilterer.filter({
          id: {
            IN: expectedCaseTypeIds
          }
        });
      });

      it('returns a list of case types including all requested case types', () => {
        expect(returnedCaseTypes)
          .toEqual(jasmine.arrayWithExactContents(expectedCaseTypes));
      });
    });

    describe('when filtering by a case type category', () => {
      beforeEach(() => {
        const prospectCategory = _.find(allCaseTypeCategories, {
          name: 'Prospecting'
        });

        expectedCaseTypes = _.filter(allCaseTypes, {
          case_type_category: prospectCategory.value
        });

        returnedCaseTypes = CaseTypeFilterer.filter({
          case_type_category: prospectCategory.name
        });
      });

      it('returns a list of case types belonging to the case type category', () => {
        expect(returnedCaseTypes).toEqual(expectedCaseTypes);
      });
    });

    describe('multiple filters', () => {
      describe('when filtering by multiple case type ids that belong to a particular category', () => {
        beforeEach(() => {
          const prospectCategory = _.find(allCaseTypeCategories, {
            name: 'Prospecting'
          });
          const prospectCaseTypes = _.filter(allCaseTypes, {
            case_type_category: prospectCategory.value
          });

          expectedCaseTypes = _.sample(prospectCaseTypes, 1);

          returnedCaseTypes = CaseTypeFilterer.filter({
            case_type_category: prospectCategory.name,
            id: {
              IN: _.map(expectedCaseTypes, 'id')
            }
          });
        });

        it('returns a list of case types filtered by multiple parameters', () => {
          expect(returnedCaseTypes).toEqual(expectedCaseTypes);
        });
      });
    });

    describe('active and disabled case types', () => {
      describe('when filtering without specifying the case type active field', () => {
        beforeEach(() => {
          expectedCaseTypes = _.filter(allCaseTypes, {
            is_active: '1'
          });

          returnedCaseTypes = CaseTypeFilterer.filter({});
        });

        it('returns all active case types', () => {
          expect(returnedCaseTypes).toEqual(expectedCaseTypes);
        });
      });

      describe('when filtering for active case types', () => {
        beforeEach(() => {
          expectedCaseTypes = _.filter(allCaseTypes, {
            is_active: '1'
          });

          returnedCaseTypes = CaseTypeFilterer.filter({
            is_active: '1'
          });
        });

        it('returns all active case types', () => {
          expect(returnedCaseTypes).toEqual(expectedCaseTypes);
        });
      });

      describe('when filtering for disabled case types', () => {
        beforeEach(() => {
          expectedCaseTypes = _.filter(allCaseTypes, {
            is_active: '0'
          });

          returnedCaseTypes = CaseTypeFilterer.filter({
            is_active: '0'
          });
        });

        it('returns disabled case types only', () => {
          expect(returnedCaseTypes).toEqual(expectedCaseTypes);
        });
      });
    });
  });
})(CRM._);
