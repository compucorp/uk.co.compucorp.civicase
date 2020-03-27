/* eslint-env jasmine */

(() => {
  describe('checkbox', () => {
    let $controller, $scope, event;
    const trueValue = 'this is correct';
    const falseValue = 'this is not correct';

    beforeEach(module('civicase-base'));

    beforeEach(inject((_$controller_, $rootScope) => {
      $controller = _$controller_;
      $scope = $rootScope.$new();
      $scope.ngModel = null;
      event = {
        type: null,
        keyCode: null,
        preventDefault: jasmine.createSpy('preventDefault')
      };
    }));

    describe('on init', () => {
      describe('when the model value is true', () => {
        beforeEach(() => {
          $scope.ngModel = true;

          initController({ $scope });
        });

        it('sets the checkbox as checked', () => {
          expect($scope.isChecked).toBe(true);
        });
      });

      describe('when the model value is false', () => {
        beforeEach(() => {
          $scope.ngModel = false;

          initController({ $scope });
        });

        it('sets the checkbox as checked', () => {
          expect($scope.isChecked).toBe(false);
        });
      });

      describe('when setting different true and false values', () => {
        beforeEach(() => {
          $scope.trueValue = trueValue;
          $scope.falseValue = falseValue;
        });

        describe('when the model value is "this is correct"', () => {
          beforeEach(() => {
            $scope.ngModel = trueValue;

            initController({ $scope });
          });

          it('sets the checkbox as checked', () => {
            expect($scope.isChecked).toBe(true);
          });
        });

        describe('when the model value is "this is not correct"', () => {
          beforeEach(() => {
            $scope.ngModel = falseValue;

            initController({ $scope });
          });

          it('sets the checkbox as checked', () => {
            expect($scope.isChecked).toBe(false);
          });
        });
      });
    });

    describe('toggling the checkbox', () => {
      beforeEach(() => {
        $scope.ngModel = false;

        initController({ $scope });
      });

      describe('when we click the checkbox', () => {
        beforeEach(() => {
          event.type = 'click';

          $scope.toggleCheckbox(event);
        });

        it('marks the checkbox as checked', () => {
          expect($scope.isChecked).toBe(true);
        });

        it('sets the checkbox value to true', () => {
          expect($scope.ngModel).toBe(true);
        });

        it('prevents default events from happening', () => {
          expect(event.preventDefault).toHaveBeenCalled();
        });
      });

      describe('when we press the space bar', () => {
        beforeEach(() => {
          event.type = 'keydown';
          event.keyCode = 32;

          $scope.toggleCheckbox(event);
        });

        it('marks the checkbox as checked', () => {
          expect($scope.isChecked).toBe(true);
        });

        it('sets the checkbox value to true', () => {
          expect($scope.ngModel).toBe(true);
        });

        it('prevents default events from happening', () => {
          expect(event.preventDefault).toHaveBeenCalled();
        });
      });

      describe('when we press the enter key', () => {
        beforeEach(() => {
          event.type = 'keydown';
          event.keyCode = 13;

          $scope.toggleCheckbox(event);
        });

        it('marks the checkbox as checked', () => {
          expect($scope.isChecked).toBe(true);
        });

        it('sets the checkbox value to true', () => {
          expect($scope.ngModel).toBe(true);
        });

        it('prevents default events from happening', () => {
          expect(event.preventDefault).toHaveBeenCalled();
        });
      });

      describe('when we press any other key', () => {
        beforeEach(() => {
          event.type = 'keydown';
          event.keyCode = 27;

          $scope.toggleCheckbox(event);
        });

        it('does not mark the checkbox as checked', () => {
          expect($scope.isChecked).toBe(false);
        });

        it('does not change the model value', () => {
          expect($scope.ngModel).toBe(false);
        });

        it('does not prevents the event from happening', () => {
          expect(event.preventDefault).not.toHaveBeenCalled();
        });
      });

      describe('when we trigger any other type of event', () => {
        beforeEach(() => {
          event.type = 'hover';

          $scope.toggleCheckbox(event);
        });

        it('does not mark the checkbox as checked', () => {
          expect($scope.isChecked).toBe(false);
        });

        it('does not change the model value', () => {
          expect($scope.ngModel).toBe(false);
        });

        it('does not prevents the event from happening', () => {
          expect(event.preventDefault).not.toHaveBeenCalled();
        });
      });
    });

    describe('toggling the checkbox using custom values', () => {
      beforeEach(() => {
        event.type = 'click';
        $scope.trueValue = trueValue;
        $scope.falseValue = falseValue;
        $scope.ngModel = falseValue;

        initController({ $scope });
      });

      describe('when toggling from the false value to the true value', () => {
        beforeEach(() => {
          $scope.toggleCheckbox(event);
        });

        it('sets the model value to the true value', () => {
          expect($scope.ngModel).toBe(trueValue);
        });
      });

      describe('when toggling from the true value to the false value', () => {
        beforeEach(() => {
          $scope.toggleCheckbox(event);
          $scope.toggleCheckbox(event);
        });

        it('sets the model value to the false value', () => {
          expect($scope.ngModel).toBe(falseValue);
        });
      });
    });

    /**
     * Initialises the checkbox controller using the given dependencies.
     *
     * @param {object} dependencies a list of dependencies.
     */
    function initController (dependencies) {
      $controller('civicaseCheckboxController', dependencies);
    }
  });
})();
