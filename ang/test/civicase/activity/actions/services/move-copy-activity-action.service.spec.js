/* eslint-env jasmine */

(function (_, $) {
  describe('MoveCopyActivityAction', function () {
    var $q, $rootScope, MoveCopyActivityAction, activitiesMockData,
      crmApiMock, dialogServiceMock;

    beforeEach(module('civicase', 'civicase.data', function ($provide) {
      crmApiMock = jasmine.createSpy('crmApi');
      dialogServiceMock = jasmine.createSpyObj('dialogService', ['open']);

      $provide.value('crmApi', crmApiMock);
      $provide.value('dialogService', dialogServiceMock);
    }));

    beforeEach(inject(function (_$q_, _$rootScope_, _activitiesMockData_,
      _MoveCopyActivityAction_) {
      $q = _$q_;
      $rootScope = _$rootScope_;
      activitiesMockData = _activitiesMockData_;
      MoveCopyActivityAction = _MoveCopyActivityAction_;
    }));

    describe('Copy Activities bulk action', function () {
      var activities, modalOpenCall, model, selectedActivities;

      beforeEach(function () {
        var caseId = _.uniqueId();

        activities = activitiesMockData.get();

        activities.forEach(function (activity) {
          activity.case_id = caseId;
        });

        selectedActivities = _.sample(activities, 2);
      });

      describe('when selecting some activities and then copy them to a new case', function () {
        beforeEach(function () {
          MoveCopyActivityAction.moveCopyActivities(selectedActivities, 'copy');

          modalOpenCall = dialogServiceMock.open.calls.mostRecent().args;
          model = modalOpenCall[2];
        });

        it('opens a case selection modal', function () {
          expect(dialogServiceMock.open).toHaveBeenCalledWith(
            'MoveCopyActCard',
            '~/civicase/activity/actions/services/move-copy-activity-action.html',
            jasmine.any(Object),
            jasmine.any(Object)
          );
        });

        it('displays the title as "Copy 2 Activities"', function () {
          expect(modalOpenCall[3].title).toBe('Copy 2 Activities');
        });

        describe('the model', function () {
          it('defines an empty case id', function () {
            expect(model.case_id).toBe('');
          });

          it('does not display the subject', function () {
            expect(model.isSubjectVisible).toBe(false);
          });

          it('defines an empty subject', function () {
            expect(model.subject).toBe('');
          });
        });

        describe('when saving the copy action modal', function () {
          var expectedActivitySavingCalls;

          beforeEach(function () {
            var saveMethod = modalOpenCall[3].buttons[0].click;
            model.case_id = _.uniqueId();
            model.subject = 'subject';
            expectedActivitySavingCalls = [['Activity', 'copybyquery', {
              case_id: model.case_id,
              subject: model.subject,
              id: selectedActivities.map(function (activity) {
                return activity.id;
              })
            }]];

            spyOn($.fn, 'dialog');
            spyOn($rootScope, '$broadcast');
            crmApiMock.and.returnValue($q.resolve([{ values: selectedActivities }]));
            saveMethod();
            $rootScope.$digest();
          });

          it('saves a new copy of each of the activities and assign them to the selected case', function () {
            expect(crmApiMock.calls.mostRecent().args[0]).toEqual(expectedActivitySavingCalls);
          });

          it('emits a civicase activity updated event', function () {
            expect($rootScope.$broadcast).toHaveBeenCalledWith('civicase::activity::updated');
          });

          it('closes the dialog', function () {
            expect($.fn.dialog).toHaveBeenCalled();
          });
        });

        describe('when the selected case is the same as the current case', function () {
          beforeEach(function () {
            var saveMethod = modalOpenCall[3].buttons[0].click;
            model.case_id = selectedActivities[0].case_id;

            spyOn($.fn, 'dialog');
            spyOn($rootScope, '$broadcast');
            crmApiMock.and.returnValue($q.resolve([{ values: selectedActivities }]));
            saveMethod();
            $rootScope.$digest();
          });

          it('does not request the activities data', function () {
            expect(crmApiMock).not.toHaveBeenCalled();
          });

          it('does not emit the civicase activity updated event', function () {
            expect($rootScope.$broadcast).not.toHaveBeenCalledWith('civicase::activity::updated');
          });

          it('closes the dialog', function () {
            expect($.fn.dialog).toHaveBeenCalled();
          });
        });
      });

      describe('when selecting a single activity and copying it to a new case', function () {
        beforeEach(function () {
          selectedActivities = _.sample(activities, 1);

          MoveCopyActivityAction.moveCopyActivities(selectedActivities, 'copy');

          modalOpenCall = dialogServiceMock.open.calls.mostRecent().args;
          model = modalOpenCall[2];
        });

        it('displays the modal title as "Copy Type Activity"', function () {
          // @FIX: the activity at this point only has the id, source_contact_id properties. The type field is not defined:
          expect(modalOpenCall[3].title).toBe('Copy Activity');
        });

        describe('the model', function () {
          it('defines the case id the same as the selected activity', function () {
            expect(model.case_id).toBe(selectedActivities[0].case_id);
          });

          it('displays the subject', function () {
            expect(model.isSubjectVisible).toBe(true);
          });

          it('defines an empty subject', function () {
            expect(model.subject).toBe(selectedActivities[0].subject);
          });
        });
      });
    });

    describe('Move Activities bulk action', function () {
      var activities, modalOpenCall, model, selectedActivities;

      beforeEach(function () {
        var caseId = _.uniqueId();

        activities = activitiesMockData.get();

        activities.forEach(function (activity) {
          activity.case_id = caseId;
        });

        selectedActivities = _.sample(activities, 2);
      });

      describe('when selecting some activities and then move them to a new case', function () {
        beforeEach(function () {
          MoveCopyActivityAction.moveCopyActivities(selectedActivities, 'move');

          modalOpenCall = dialogServiceMock.open.calls.mostRecent().args;
          model = modalOpenCall[2];
        });

        it('opens a case selection modal', function () {
          expect(dialogServiceMock.open).toHaveBeenCalledWith(
            'MoveCopyActCard',
            '~/civicase/activity/actions/services/move-copy-activity-action.html',
            jasmine.any(Object),
            jasmine.any(Object)
          );
        });

        it('displays the title as "Move 2 Activities"', function () {
          expect(modalOpenCall[3].title).toBe('Move 2 Activities');
        });

        describe('the model', function () {
          it('defines an empty case id', function () {
            expect(model.case_id).toBe('');
          });

          it('does not display the subject', function () {
            expect(model.isSubjectVisible).toBe(false);
          });

          it('defines an empty subject', function () {
            expect(model.subject).toBe('');
          });
        });

        describe('when saving the move action modal', function () {
          var expectedActivitySavingCalls;

          beforeEach(function () {
            var saveMethod = modalOpenCall[3].buttons[0].click;
            model.case_id = _.uniqueId();
            model.subject = 'subject';
            expectedActivitySavingCalls = [['Activity', 'movebyquery', {
              case_id: model.case_id,
              subject: model.subject,
              id: selectedActivities.map(function (activity) {
                return activity.id;
              })
            }]];

            spyOn($.fn, 'dialog');
            spyOn($rootScope, '$broadcast');
            crmApiMock.and.returnValue($q.resolve([{ values: selectedActivities }]));
            saveMethod();
            $rootScope.$digest();
          });

          it('moves each of the activities and assign them to the selected case', function () {
            expect(crmApiMock.calls.mostRecent().args[0]).toEqual(expectedActivitySavingCalls);
          });

          it('emits a civicase activity updated event', function () {
            expect($rootScope.$broadcast).toHaveBeenCalledWith('civicase::activity::updated');
          });

          it('closes the dialog', function () {
            expect($.fn.dialog).toHaveBeenCalled();
          });
        });

        describe('when the selected case is the same as the current case', function () {
          beforeEach(function () {
            var saveMethod = modalOpenCall[3].buttons[0].click;
            model.case_id = selectedActivities[0].case_id;

            spyOn($.fn, 'dialog');
            spyOn($rootScope, '$broadcast');
            crmApiMock.and.returnValue($q.resolve([{ values: selectedActivities }]));
            saveMethod();
            $rootScope.$digest();
          });

          it('does not request the activities data', function () {
            expect(crmApiMock).not.toHaveBeenCalled();
          });

          it('does not emit the civicase activity updated event', function () {
            expect($rootScope.$broadcast).not.toHaveBeenCalledWith('civicase::activity::updated');
          });

          it('closes the dialog', function () {
            expect($.fn.dialog).toHaveBeenCalled();
          });
        });
      });

      describe('when selecting a single activity and moving it to a new case', function () {
        beforeEach(function () {
          selectedActivities = _.sample(activities, 1);

          MoveCopyActivityAction.moveCopyActivities(selectedActivities, 'move');

          modalOpenCall = dialogServiceMock.open.calls.mostRecent().args;
          model = modalOpenCall[2];
        });

        it('displays the modal title as "Move Type Activity"', function () {
          // @FIX: the activity at this point only has the id, source_contact_id properties. The type field is not defined:
          expect(modalOpenCall[3].title).toBe('Move Activity');
        });

        describe('the model', function () {
          it('defines the case id the same as the selected activity', function () {
            expect(model.case_id).toBe(selectedActivities[0].case_id);
          });

          it('displays the subject', function () {
            expect(model.isSubjectVisible).toBe(true);
          });

          it('defines an empty subject', function () {
            expect(model.subject).toBe(selectedActivities[0].subject);
          });
        });
      });
    });
  });
})(CRM._, CRM.$);
