/* eslint-env jasmine */

((_) => {
  describe('Case Type', () => {
    let CaseType, CaseTypesData, CaseTypesMockDataProvider;

    beforeEach(module('civicase', 'civicase.data', (_CaseTypesMockDataProvider_) => {
      CaseTypesMockDataProvider = _CaseTypesMockDataProvider_;

      CaseTypesMockDataProvider.add({
        title: 'inactive case type',
        is_active: '0'
      });
    }));

    afterEach(() => {
      CaseTypesMockDataProvider.restore();
    });

    beforeEach(inject((_CaseType_, _CaseTypesMockData_) => {
      CaseType = _CaseType_;
      CaseTypesData = _CaseTypesMockData_.get();
    }));

    describe('when getting all active case types', () => {
      let activeCaseTypes, returnedCaseTypes;

      beforeEach(() => {
        activeCaseTypes = _.pick(CaseTypesData, (caseType) => caseType.is_active === '1');
        returnedCaseTypes = CaseType.getAll();
      });

      it('returns all the active case types', () => {
        expect(returnedCaseTypes).toEqual(activeCaseTypes);
      });
    });

    describe('when getting all case including inactive ones', () => {
      let returnedCaseTypes;

      beforeEach(() => {
        returnedCaseTypes = CaseType.getAll({ includeInactive: true });
      });

      it('returns all the active case types', () => {
        expect(returnedCaseTypes).toEqual(CaseTypesData);
      });
    });

    describe('when getting the titles for case types using their name', () => {
      let returnedTitles;

      beforeEach(() => {
        returnedTitles = CaseType.getTitlesForNames([
          'housing_support',
          'adult_day_care_referral'
        ]);
      });

      it('returns the title for the given case types', () => {
        expect(returnedTitles).toEqual([
          'Housing Support',
          'Adult Day Care Referral'
        ]);
      });
    });
  });
})(CRM._);
