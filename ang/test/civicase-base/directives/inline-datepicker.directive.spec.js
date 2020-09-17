/* eslint-env jasmine */

(($) => {
  describe('civicaseInlineDatepicker', () => {
    const NG_INVALID_CLASS = 'ng-invalid';
    let $compile, $rootScope, $scope, dateInputFormatValue, element,
      originalDatepickerFunction, removeDatePickerHrefs;

    beforeEach(module('civicase-base', 'civicase.data'));

    beforeEach(inject((_$compile_, _$rootScope_, _dateInputFormatValue_,
      _removeDatePickerHrefs_) => {
      $compile = _$compile_;
      $rootScope = _$rootScope_;
      dateInputFormatValue = _dateInputFormatValue_;
      removeDatePickerHrefs = _removeDatePickerHrefs_;
      $scope = $rootScope.$new();
      originalDatepickerFunction = $.fn.datepicker;
      $.fn.datepicker = jasmine.createSpy('datepicker');
      moment.suppressDeprecationWarnings = true;
    }));

    afterEach(() => {
      $.fn.datepicker = originalDatepickerFunction;
      moment.suppressDeprecationWarnings = false;
    });

    describe('when the directive is initialised', () => {
      beforeEach(() => {
        initDirective();
      });

      it('sets the element as a datepicker input element', () => {
        expect($.fn.datepicker).toHaveBeenCalled();
      });

      it('sets the date format as the one specified by CiviCRM setting', () => {
        expect($.fn.datepicker).toHaveBeenCalledWith(jasmine.objectContaining({
          dateFormat: dateInputFormatValue
        }));
      });

      it('does not change the site url wrongly when selecting a date', () => {
        expect($.fn.datepicker).toHaveBeenCalledWith(jasmine.objectContaining({
          beforeShow: removeDatePickerHrefs,
          onChangeMonthYear: removeDatePickerHrefs
        }));
      });
    });

    describe('input format', () => {
      describe('when the value is initially given', () => {
        beforeEach(() => {
          $scope.date = '1999-01-31';

          initDirective();
        });

        it('sets the input format in day/month/year', () => {
          expect(element.val()).toBe('31/01/1999');
        });

        it('keeps the model value in the year-month-day format', () => {
          expect($scope.date).toBe('1999-01-31');
        });
      });

      describe('when the value is updated', () => {
        beforeEach(() => {
          $scope.date = '1999-01-31';

          initDirective();
          element.val('28/02/1999');
          element.change();
          $scope.$digest();
        });

        it('sets the input format in day/month/year', () => {
          expect(element.val()).toBe('28/02/1999');
        });

        it('keeps the model value in the year-month-day format', () => {
          expect($scope.date).toBe('1999-02-28');
        });
      });
    });

    describe('validation', () => {
      describe('when changing to a invalid date format', () => {
        beforeEach(() => {
          $scope.date = '1999-01-31';

          initDirective();
          element.val('28/02');
          element.change();
          $scope.$digest();
        });

        it('marks the input as invalid', () => {
          expect(element.hasClass(NG_INVALID_CLASS)).toBe(true);
        });
      });

      describe('when changing to a valid date format', () => {
        beforeEach(() => {
          $scope.date = '1999-01-31';

          initDirective();
          element.val('28/02');
          element.change();
          $scope.$digest();
          element.val('28/02/1999');
          element.change();
          $scope.$digest();
        });

        it('marks the input as valid', () => {
          expect(element.hasClass(NG_INVALID_CLASS)).toBe(false);
        });
      });
    });

    /**
     * Initialises the Inline Datepicker directive on an input element using
     * the global $scope variable.
     */
    function initDirective () {
      element = $compile(`
        <input
          civicase-inline-datepicker
          ng-model="date"
          type="text"
        />
      `)($scope);
      $scope.$digest();
    }
  });
})(CRM.$);
