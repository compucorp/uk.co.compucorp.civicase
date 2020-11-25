/* eslint-env jasmine */

(($, _) => {
  describe('civicaseInlineDatepicker', () => {
    const NG_INVALID_CLASS = 'ng-invalid';
    let $compile, $rootScope, $scope, dateInputFormatValue, element,
      originalDatepickerFunction, removeDatePickerHrefs;
    var API_DATE_FORMAT = 'yy-mm-dd';

    beforeEach(module('civicase-base', 'civicase.data', ($provide) => {
      removeDatePickerHrefs = jasmine.createSpy('removeDatePickerHrefs');

      $provide.value('removeDatePickerHrefs', removeDatePickerHrefs);
    }));

    beforeEach(inject((_$compile_, _$rootScope_, _dateInputFormatValue_) => {
      $compile = _$compile_;
      $rootScope = _$rootScope_;
      dateInputFormatValue = _dateInputFormatValue_;
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
      describe('general properties', () => {
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
      });

      describe('mininum and maximum date properties', () => {
        describe('when minimum and maximum dates are provided', () => {
          beforeEach(() => {
            $scope.minDate = '1999-01-01';
            $scope.maxDate = '1999-01-31';

            initDirective();
          });

          it('sets the datepicker minimum date as provided by the directive attributes', () => {
            expect($.fn.datepicker).toHaveBeenCalledWith(jasmine.objectContaining({
              minDate: $.datepicker.parseDate(API_DATE_FORMAT, $scope.minDate)
            }));
          });

          it('sets the datepicker minimum date as provided by the directive attributes', () => {
            expect($.fn.datepicker).toHaveBeenCalledWith(jasmine.objectContaining({
              maxDate: $.datepicker.parseDate(API_DATE_FORMAT, $scope.maxDate)
            }));
          });
        });

        describe('when no minimum and maximum dates are provided', () => {
          beforeEach(() => {
            initDirective();
          });

          it('does not define a minimum date for the datepicker', () => {
            expect($.fn.datepicker).toHaveBeenCalledWith(jasmine.objectContaining({
              minDate: null
            }));
          });

          it('does not define a maximum date for the datepicker', () => {
            expect($.fn.datepicker).toHaveBeenCalledWith(jasmine.objectContaining({
              maxDate: null
            }));
          });
        });
      });
    });

    describe('handling the datepicker events', () => {
      beforeEach(() => {
        initDirective();
      });

      describe('when the datepicker is opened', () => {
        beforeEach(() => {
          const beforeShow = $.fn.datepicker.calls.first()
            .args[0].beforeShow || _.noop;

          beforeShow(1, 2, 3, 4);
        });

        it('does not change the site url wrongly when selecting a date', () => {
          expect(removeDatePickerHrefs).toHaveBeenCalledWith(1, 2, 3, 4);
        });

        it('keeps displaying the input even after moving out to select a date', () => {
          expect(element.hasClass('civicase__inline-datepicker--open')).toBe(true);
        });
      });

      describe('when the datepicker month or year changes', () => {
        beforeEach(() => {
          const onChangeMonthYear = $.fn.datepicker.calls.first()
            .args[0].onChangeMonthYear || _.noop;

          onChangeMonthYear(1, 2, 3, 4);
        });

        it('does not change the site url wrongly when selecting a date', () => {
          expect(removeDatePickerHrefs).toHaveBeenCalledWith(1, 2, 3, 4);
        });
      });

      describe('when the datepicker is closed', () => {
        beforeEach(() => {
          const onClose = $.fn.datepicker.calls.first()
            .args[0].onClose || _.noop;

          onClose();
        });

        it('hides the input element if not directly hovering it', () => {
          expect(element.hasClass('civicase__inline-datepicker--open')).toBe(false);
        });
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

    describe('minimum and maximum attribute changes', () => {
      describe('when the minimum attribute changes', () => {
        beforeEach(() => {
          $scope.minDate = '1999-01-01';

          initDirective();

          $scope.minDate = '1999-02-01';

          $scope.$digest();
        });

        it('updates the minimum date for the datepicker', () => {
          expect($.fn.datepicker).toHaveBeenCalledWith(
            'option',
            'minDate',
            $.datepicker.parseDate(API_DATE_FORMAT, $scope.minDate)
          );
        });
      });

      describe('when the maximum attribute changes', () => {
        beforeEach(() => {
          $scope.maxDate = '1999-12-31';

          initDirective();

          $scope.maxDate = '1999-11-30';

          $scope.$digest();
        });

        it('updates the minimum date for the datepicker', () => {
          expect($.fn.datepicker).toHaveBeenCalledWith(
            'option',
            'maxDate',
            $.datepicker.parseDate(API_DATE_FORMAT, $scope.maxDate)
          );
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
          data-min-date="{{ minDate }}"
          data-max-date="{{ maxDate }}"
          ng-model="date"
          type="text"
        />
      `)($scope);
      $scope.$digest();
    }
  });
})(CRM.$, CRM._);
