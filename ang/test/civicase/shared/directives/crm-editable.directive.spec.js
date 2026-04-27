/* eslint-env jasmine */
(function ($, _) {
  describe('crmEditable (rich-text)', function () {
    // eslint-disable-next-line no-unused-vars
    var $compile, $rootScope, $scope, $q, $timeout, element, wrapper;
    var originalCRM, originalCrmEditable, originalCrmEditableEntity;
    var crmApi3Spy, crmApi4Spy, alertSpy, loadScriptDeferred,
      wysiwygCreateDeferred, currentTextareaValue;

    beforeEach(module('civicase.templates', 'civicase', 'civicase.data'));

    beforeEach(inject(function (_$compile_, _$rootScope_, _$q_, _$timeout_) {
      $compile = _$compile_;
      $rootScope = _$rootScope_;
      $q = _$q_;
      $timeout = _$timeout_;
      $scope = $rootScope.$new();

      // Snapshot CRM-level globals we are about to override so we can put
      // them back in afterEach.
      originalCRM = {
        loadScript: CRM.loadScript,
        wysiwyg: CRM.wysiwyg,
        api3: CRM.api3,
        api4: CRM.api4,
        alert: CRM.alert,
        utils: CRM.utils
      };
      originalCrmEditable = $.fn.crmEditable;
      originalCrmEditableEntity = $.fn.crmEditableEntity;

      loadScriptDeferred = $.Deferred().resolve();
      CRM.loadScript = jasmine.createSpy('loadScript')
        .and.returnValue(loadScriptDeferred);

      // Stub the legacy jQuery plugin so we can detect (or not) calls to it.
      $.fn.crmEditable = jasmine.createSpy('crmEditable');
      // crmEditableEntity is what the directive uses to read entity / id.
      $.fn.crmEditableEntity = function () {
        return {
          entity: 'Case',
          id: 42,
          action: 'create',
          field: 'details',
          params: {}
        };
      };

      // Stub CRM.wysiwyg.
      wysiwygCreateDeferred = $.Deferred().resolve();
      currentTextareaValue = '<p>edited via wysiwyg</p>';
      CRM.wysiwyg = {
        create: jasmine.createSpy('wysiwygCreate')
          .and.returnValue(wysiwygCreateDeferred),
        setVal: jasmine.createSpy('wysiwygSetVal'),
        getVal: jasmine.createSpy('wysiwygGetVal')
          .and.callFake(function () { return currentTextareaValue; }),
        destroy: jasmine.createSpy('wysiwygDestroy')
      };

      // API stubs default to never-resolving deferreds; individual tests
      // override .and.returnValue() with the response shape they need.
      crmApi3Spy = jasmine.createSpy('api3').and.returnValue($.Deferred());
      crmApi4Spy = jasmine.createSpy('api4').and.returnValue($.Deferred());
      CRM.api3 = crmApi3Spy;
      CRM.api4 = crmApi4Spy;

      alertSpy = jasmine.createSpy('alert');
      CRM.alert = alertSpy;

      // CRM.utils may not exist in the test harness; the directive
      // detects it before calling purifyHtml so we leave it alone here.
      CRM.utils = CRM.utils || {};
    }));

    afterEach(function () {
      _.each(originalCRM, function (value, key) {
        if (typeof value === 'undefined') {
          delete CRM[key];
        } else {
          CRM[key] = value;
        }
      });
      $.fn.crmEditable = originalCrmEditable;
      $.fn.crmEditableEntity = originalCrmEditableEntity;
      delete CRM.utils.purifyHtml;
    });

    describe('display mode — rich text', function () {
      describe('renders HTML rather than escaping it', function () {
        beforeEach(function () {
          $scope.item = { id: 42, details: '<p>Hello <strong>world</strong></p>' };
          compileRichText();
        });

        it('preserves the inline tags as real DOM elements', function () {
          expect(element.find('strong').length).toBe(1);
          expect(element.find('p').length).toBe(1);
        });

        it('does not escape the markup as text', function () {
          expect(element.html()).not.toContain('&lt;p&gt;');
        });

        it('does not bind the legacy jQuery jeditable plugin', function () {
          expect($.fn.crmEditable).not.toHaveBeenCalled();
        });

        it('marks the element as click-to-edit', function () {
          expect(element.hasClass('civicase__rich-text-editable')).toBe(true);
          expect(element.hasClass('crm-editable-enabled')).toBe(true);
        });
      });

      describe('defence-in-depth purification (no CRM.utils.purifyHtml)', function () {
        beforeEach(function () {
          $scope.item = {
            id: 42,
            details: '<p onclick="alert(1)">x</p>' +
              '<a href="javascript:alert(1)">y</a>'
          };
          compileRichText();
        });

        it('strips inline event-handler attributes', function () {
          expect(element.find('p').attr('onclick')).toBeUndefined();
        });

        it('strips javascript: URLs from href', function () {
          expect(element.find('a').attr('href')).toBeUndefined();
        });
      });

      describe('uses CRM.utils.purifyHtml when core exposes it', function () {
        beforeEach(function () {
          CRM.utils.purifyHtml = jasmine.createSpy('purifyHtml')
            .and.returnValue('<p>SAFE</p>');
          $scope.item = { id: 42, details: '<p>raw</p>' };
          compileRichText();
        });

        it('routes the value through CRM.utils.purifyHtml', function () {
          expect(CRM.utils.purifyHtml).toHaveBeenCalledWith('<p>raw</p>');
        });

        it('renders the purified output', function () {
          expect(element.html()).toContain('SAFE');
        });
      });

      describe('placeholder fallback for empty values', function () {
        beforeEach(function () {
          $scope.item = { id: 42, details: '' };
          compileRichText();
        });

        it('shows the placeholder text', function () {
          expect(element.text()).toContain('Click to add notes');
        });
      });
    });

    describe('display mode — plain textarea (regression)', function () {
      beforeEach(function () {
        $scope.item = { description: '<b>raw</b>' };
        wrapper = angular.element(
          '<div class="crm-entity" data-entity="Case" data-id="42">' +
            '<div crm-editable="item" data-field="description" ' +
            'data-type="textarea"></div>' +
          '</div>'
        );
        $compile(wrapper)($scope);
        $scope.$digest();
        $rootScope.$apply();
        element = wrapper.find('[crm-editable]');
      });

      it('still escapes HTML so plain text is shown literally', function () {
        expect(element.find('b').length).toBe(0);
        expect(element.text()).toContain('<b>raw</b>');
      });

      it('still hands off to the legacy jeditable plugin', function () {
        expect($.fn.crmEditable).toHaveBeenCalled();
      });
    });

    describe('inline editing', function () {
      beforeEach(function () {
        $scope.item = { id: 42, details: '<p>Initial</p>' };
        compileRichText();
      });

      it('opens a CKEditor textarea on click', function () {
        element.click();
        $scope.$digest();
        expect(CRM.wysiwyg.create).toHaveBeenCalled();
        var selector = CRM.wysiwyg.create.calls.mostRecent().args[0];
        expect(selector).toMatch(/^#civicase-rich-text-Case-42-details/);
      });

      it('seeds the editor with the existing HTML via setVal', function () {
        element.click();
        $scope.$digest();
        expect(CRM.wysiwyg.setVal).toHaveBeenCalledWith(
          jasmine.any(String),
          '<p>Initial</p>'
        );
      });

      it('renders an accessible label for the textarea', function () {
        element.click();
        $scope.$digest();
        var $textarea = element.find('textarea.crm-form-wysiwyg');
        expect($textarea.length).toBe(1);
        expect($textarea.attr('aria-label')).toBeTruthy();
        expect(element.find('label.sr-only').length).toBe(1);
        expect(element.find('label.sr-only').attr('for'))
          .toBe($textarea.attr('id'));
      });

      it('marks the wrapper while editing so model watchers don\'t clobber it', function () {
        element.click();
        $scope.$digest();
        expect(element.hasClass('civicase__rich-text-editing')).toBe(true);
      });

      it('ignores clicks on inner anchors / buttons', function () {
        element.html('<p><a href="#x">link</a></p>');
        element.find('a').trigger('click');
        $scope.$digest();
        expect(CRM.wysiwyg.create).not.toHaveBeenCalled();
      });

      describe('saving (defaults to APIv4)', function () {
        beforeEach(function () {
          element.click();
          $scope.$digest();
        });

        it('calls CRM.api4 with Case.update', function () {
          var deferred = $.Deferred().resolve([
            { id: 42, details: '<p>edited via wysiwyg</p>' }
          ]);
          crmApi4Spy.and.returnValue(deferred);
          element.find('.civicase__rich-text-form__btn--save').trigger('click');
          $scope.$digest();
          expect(crmApi4Spy).toHaveBeenCalledWith('Case', 'update', {
            where: [['id', '=', 42]],
            values: { details: '<p>edited via wysiwyg</p>' }
          });
        });

        it('does not fall back to APIv3', function () {
          crmApi4Spy.and.returnValue($.Deferred().resolve([
            { id: 42, details: '<p>edited via wysiwyg</p>' }
          ]));
          element.find('.civicase__rich-text-form__btn--save').trigger('click');
          $scope.$digest();
          expect(crmApi3Spy).not.toHaveBeenCalled();
        });

        it('updates the model with the saved value from the response', function () {
          crmApi4Spy.and.returnValue($.Deferred().resolve([
            { id: 42, details: '<p>SERVER_NORMALISED</p>' }
          ]));
          element.find('.civicase__rich-text-form__btn--save').trigger('click');
          $scope.$digest();
          $timeout.flush();
          expect($scope.item.details).toBe('<p>SERVER_NORMALISED</p>');
        });

        it('alerts the user on failure and keeps the editor open', function () {
          crmApi4Spy.and.returnValue($.Deferred().reject({
            error_message: 'Server exploded'
          }));
          element.find('.civicase__rich-text-form__btn--save').trigger('click');
          $scope.$digest();
          expect(alertSpy).toHaveBeenCalled();
          expect(element.hasClass('civicase__rich-text-editing')).toBe(true);
        });
      });

      describe('saving (legacy data-action falls back to APIv3)', function () {
        beforeEach(function () {
          // Re-compile with data-action="setvalue" to opt into legacy.
          wrapper.remove();
          $scope.item = { id: 42, details: '<p>Initial</p>' };
          wrapper = angular.element(
            '<div class="crm-entity" data-entity="Case" data-id="42">' +
              '<div crm-editable="item" data-field="details" ' +
              'data-type="textarea" data-rich-text="true" ' +
              'data-action="setvalue" ' +
              'data-placeholder="Click to add notes"></div>' +
            '</div>'
          );
          $compile(wrapper)($scope);
          $scope.$digest();
          $rootScope.$apply();
          element = wrapper.find('[crm-editable]');
          element.click();
          $scope.$digest();
        });

        it('uses APIv3 setvalue', function () {
          crmApi3Spy.and.returnValue($.Deferred().resolve({
            is_error: 0,
            values: [{ id: 42, details: '<p>edited via wysiwyg</p>' }]
          }));
          element.find('.civicase__rich-text-form__btn--save').trigger('click');
          $scope.$digest();
          expect(crmApi3Spy).toHaveBeenCalled();
          var args = crmApi3Spy.calls.mostRecent().args;
          expect(args[0]).toBe('Case');
          expect(args[1]).toBe('setvalue');
          expect(args[2]).toEqual(jasmine.objectContaining({
            id: 42,
            field: 'details',
            value: '<p>edited via wysiwyg</p>'
          }));
        });

        it('does not call APIv4 in legacy mode', function () {
          crmApi3Spy.and.returnValue($.Deferred().resolve({ is_error: 0 }));
          element.find('.civicase__rich-text-form__btn--save').trigger('click');
          $scope.$digest();
          expect(crmApi4Spy).not.toHaveBeenCalled();
        });
      });

      describe('cancelling', function () {
        beforeEach(function () {
          element.click();
          $scope.$digest();
          element.find('.civicase__rich-text-form__btn--cancel').trigger('click');
          $scope.$digest();
        });

        it('destroys the WYSIWYG instance', function () {
          expect(CRM.wysiwyg.destroy).toHaveBeenCalled();
        });

        it('removes the editing-state class', function () {
          expect(element.hasClass('civicase__rich-text-editing')).toBe(false);
        });

        it('does not save', function () {
          expect(crmApi3Spy).not.toHaveBeenCalled();
          expect(crmApi4Spy).not.toHaveBeenCalled();
        });

        it('restores the original rendered HTML', function () {
          expect(element.find('p').length).toBe(1);
          expect(element.text()).toContain('Initial');
        });
      });
    });

    /**
     * Compiles the directive in rich-text mode wrapped in a `crm-entity` so
     * crmEditableEntity() can resolve entity / id, and stashes the inner
     * editable element on `element` for assertions.
     */
    function compileRichText () {
      wrapper = angular.element(
        '<div class="crm-entity" data-entity="Case" data-id="42">' +
          '<div crm-editable="item" data-field="details" ' +
          'data-type="textarea" data-rich-text="true" ' +
          'data-placeholder="Click to add notes"></div>' +
        '</div>'
      );
      $compile(wrapper)($scope);
      $scope.$digest();
      // CRM.loadScript() is mocked to a resolved deferred so the directive's
      // setup runs synchronously; trigger one more digest just in case any
      // watchers were registered inside the .done() callback.
      $rootScope.$apply();
      element = wrapper.find('[crm-editable]');
    }
  });
}(CRM.$, CRM._));
