(function (angular, $, _, CRM) {
  var module = angular.module('civicase');

  // Angular binding for CiviCRM's jQuery-based crm-editable
  module.directive('crmEditable', function ($filter, $timeout) {
    var escapeString = $filter('civicaseEscapeString');

    return {
      restrict: 'A',
      link: crmEditableLink,
      scope: {
        model: '=crmEditable',
        lineLimit: '@'
      }
    };

    /**
     * Link function for crmEditable directive
     *
     * @param {object} scope scope of the directive
     * @param {object} elem element
     * @param {object} attrs attributes
     */
    function crmEditableLink (scope, elem, attrs) {
      CRM.loadScript(CRM.config.resourceBase + 'js/jquery/jquery.crmEditable.js')
        .done(function () {
          var textarea = elem.data('type') === 'textarea';
          var richText = elem.data('rich-text') === true ||
            elem.data('rich-text') === 'true' ||
            elem.data('type') === 'wysiwyg';
          var field = elem.data('field');
          elem
            .html(getDisplayHTML(scope, elem, attrs, textarea, richText))
            .on('crmFormSuccess', function (e, value) {
              $timeout(function () {
                scope.$apply(function () {
                  scope.model[field] = value;
                });
                applyLineLimitIfApplicableWithTimeout(scope, elem);
              });
            });

          if (richText) {
            setUpRichTextEditing(scope, elem, attrs, field);
          } else {
            elem.crmEditable();
          }

          scope.$watchCollection('model', function () {
            // Don't blow away the editor if the user is currently editing.
            if (elem.hasClass('civicase__rich-text-editing')) {
              return;
            }
            elem.html(getDisplayHTML(scope, elem, attrs, textarea, richText));

            applyLineLimitIfApplicableWithTimeout(scope, elem);
          });

          applyLineLimitIfApplicableWithTimeout(scope, elem);
        });
    }

    /**
     * Sets up rich-text inline editing for the given element
     *
     * @param {object} scope scope of the directive
     * @param {object} elem element
     * @param {object} attrs attributes
     * @param {string} field the model field name being edited
     */
    function setUpRichTextEditing (scope, elem, attrs, field) {
      elem.addClass('crm-editable crm-editable-enabled civicase__rich-text-editable');
      if (!elem.attr('title')) {
        elem.attr('title', ts('Click to edit'));
      }

      elem.on('click.civicaseRichTextEdit', function (event) {
        if (elem.hasClass('civicase__rich-text-editing') ||
          $(event.target).closest('a, button, input, textarea, select').length) {
          return;
        }

        event.preventDefault();
        startRichTextEdit(scope, elem, attrs, field);
      });
    }

    /**
     * Replaces the element's contents with a CKEditor instance pre-populated with the current value.
     *
     * @param {object} scope scope of the directive
     * @param {object} elem element
     * @param {object} attrs attributes
     * @param {string} field the model field name being edited
     */
    function startRichTextEdit (scope, elem, attrs, field) {
      var info = elem.crmEditableEntity();

      if (!info || !info.entity || !info.id) {
        return;
      }

      var originalValue = scope.model[field] || '';
      var escapedInitial = $('<div>').text(originalValue).html();
      var editorId = 'civicase-rich-text-' + info.entity + '-' + info.id +
        '-' + field + '-' + Date.now();
      var editorLabel = elem.data('label') ||
        attrs.placeholder ||
        field;
      var $form = $(
        '<div class="civicase__rich-text-form">' +
          '<label class="sr-only" for="' + editorId + '">' +
            _.escape(editorLabel) +
          '</label>' +
          '<textarea class="crm-form-wysiwyg" id="' + editorId + '" ' +
            'name="' + _.escape(field) + '" ' +
            'aria-label="' + _.escape(editorLabel) + '" ' +
            'rows="6">' + escapedInitial + '</textarea>' +
          '<div class="civicase__rich-text-form__actions" ' +
            'style="margin-top: 6px; text-align: right;">' +
            '<button type="button" ' +
              'class="civicase__rich-text-form__btn ' +
                'civicase__rich-text-form__btn--save crm-button crm-form-submit" ' +
              'title="' + ts('Save') + '" ' +
              'aria-label="' + ts('Save') + '" ' +
              'style="margin-right: 4px;">' +
              '<i class="crm-i fa-check" aria-hidden="true"></i>' +
            '</button>' +
            '<button type="button" ' +
              'class="civicase__rich-text-form__btn ' +
                'civicase__rich-text-form__btn--cancel crm-button cancel" ' +
              'title="' + ts('Cancel') + '" ' +
              'aria-label="' + ts('Cancel') + '">' +
              '<i class="crm-i fa-times" aria-hidden="true"></i>' +
            '</button>' +
          '</div>' +
        '</div>'
      );
      var previousHtml = elem.html();

      elem.addClass('civicase__rich-text-editing crm-editable-editing');
      elem.empty().append($form);

      CRM.wysiwyg.create('#' + editorId).done(function () {
        CRM.wysiwyg.setVal('#' + editorId, originalValue);
      });

      $form.find('.civicase__rich-text-form__btn--save')
        .on('click', function () { saveRichTextEdit(); });
      $form.find('.civicase__rich-text-form__btn--cancel')
        .on('click', function () { cancelRichTextEdit(); });

      /**
       * Tears down the editor and restores the rendered display markup.
       *
       * @param {string} html markup to restore inside the element
       */
      function teardown (html) {
        try {
          CRM.wysiwyg.destroy('#' + editorId);
        } catch (e) { /* destroy is best-effort; ignore failures */ }
        $form.remove();
        elem.removeClass(
          'civicase__rich-text-editing crm-editable-editing crm-editable-saving'
        );
        elem.html(html);
      }

      /**
       * Cancels the in-progress edit and restores the previous markup.
       */
      function cancelRichTextEdit () {
        teardown(previousHtml);
      }

      /**
       * Persists the new value via CRM.api4 then triggers `crmFormSuccess`
       * so the directive's existing handler updates the model and refreshes
       * the rendered display.
       *
       * APIv4 is preferred for new code (project convention). The legacy
       * jeditable callback in jquery.crmEditable.js still uses APIv3 and
       * supports a `setvalue` pseudo-action; APIv4 has no equivalent —
       * `update` covers the same use case and is what we use here. If a
       * caller has explicitly opted into APIv3 by setting `data-action`
       * to "create"/"setvalue" on the element, we still honour that for
       * backwards compatibility (with a console warning), otherwise we go
       * straight to APIv4 `update`.
       */
      function saveRichTextEdit () {
        var newValue = CRM.wysiwyg.getVal('#' + editorId);
        var explicitAction = elem.data('action');
        var values = {};
        values[field] = newValue;

        elem.addClass('crm-editable-saving');

        var apiPromise;
        if (explicitAction === 'setvalue' || explicitAction === 'create') {
          // Preserve legacy behaviour for any consumer still relying on
          // APIv3 actions via `data-action`. Should be removed once all
          // call sites have been migrated.
          if (window.console && console.warn) {
            console.warn(
              '[civicase] crm-editable: data-action="' + explicitAction +
              '" uses APIv3. New code should drop data-action and let the' +
              ' directive use APIv4 update.'
            );
          }
          var legacyParams = _.extend({}, info.params || {});
          if (info.id && info.id !== 'new') {
            legacyParams.id = info.id;
          }
          if (explicitAction === 'setvalue') {
            legacyParams.field = field;
            legacyParams.value = newValue;
          } else {
            legacyParams[field] = newValue;
          }
          apiPromise = CRM.api3(info.entity, explicitAction, legacyParams, { error: null });
        } else {
          apiPromise = CRM.api4(info.entity, 'update', {
            where: [['id', '=', info.id]],
            values: values
          });
        }

        // Both CRM.api3 (jQuery Deferred) and CRM.api4 (Promise/Deferred)
        // support .then(onSuccess, onError), so we use that uniformly.
        apiPromise.then(
          function (data) {
            // APIv3 surfaces errors via `data.is_error`; APIv4 rejects.
            // Handle the APIv3 error shape here for the legacy branch.
            if (data && data.is_error) {
              elem.removeClass('crm-editable-saving');
              CRM.alert(
                (data && data.error_message) ||
                  ts('Sorry an error occurred and your information was not saved'),
                ts('Error'),
                'error'
              );
              return;
            }
            var savedValue = newValue;
            var record;
            if (_.isArray(data) && data.length) {
              record = data[0];
            } else if (data && data.values) {
              record = _.isArray(data.values)
                ? data.values[0]
                : (data.values[info.id] || data.values);
            }
            if (record && typeof record[field] !== 'undefined') {
              savedValue = record[field];
            }
            teardown('');
            elem.trigger('crmFormSuccess', [savedValue]);
          },
          function (error) {
            elem.removeClass('crm-editable-saving');
            CRM.alert(
              (error && error.error_message) ||
                ts('Sorry an error occurred and your information was not saved'),
              ts('Error'),
              'error'
            );
          }
        );
      }
    }

    /**
     * Builds the markup placed inside the editable element when not in edit mode.
     *
     * @param {object} scope scope object
     * @param {object} elem element
     * @param {object} attrs attributes
     * @param {boolean} textarea whether the element uses textarea editing
     * @param {boolean} richText whether the field stores HTML (rich text)
     * @returns {string} HTML to render in display mode
     */
    function getDisplayHTML (scope, elem, attrs, textarea, richText) {
      var field = elem.data('field');
      var placeholder = attrs.placeholder;
      var value = scope.model[field];
      var hasValue = value !== null && typeof value !== 'undefined' && value !== '';

      if (!hasValue) {
        return placeholder;
      }

      if (richText) {
        return purifyHtml(value);
      }

      var escaped = escapeString(value);

      return textarea ? nl2br(escaped) : escaped;
    }

    /**
     * Defence-in-depth client-side HTML sanitiser for rich-text values.
     *
     * @param {string} value the HTML string to sanitise
     * @returns {string} sanitised HTML string
     */
    function purifyHtml (value) {
      if (CRM.utils && typeof CRM.utils.purifyHtml === 'function') {
        return CRM.utils.purifyHtml(value);
      }

      var nodes = $.parseHTML(value, document, false);
      var $tmp = $('<div>').append(nodes);
      // Strip inline event handlers (onclick, onerror, ...) and javascript:
      // URLs from any element that survived the parse.
      $tmp.find('*').each(function () {
        var attrs = this.attributes;
        for (var i = attrs.length - 1; i >= 0; i--) {
          var name = attrs[i].name;
          var attrValue = attrs[i].value || '';
          if (/^on/i.test(name) ||
            (/^(href|src|xlink:href)$/i.test(name) &&
              /^\s*javascript:/i.test(attrValue))) {
            this.removeAttribute(name);
          }
        }
      });
      return $tmp.html();
    }

    /**
     * Converts New Line to HTML Break markup
     *
     * @param {string} string string to convert
     * @returns {string} converted string
     */
    function nl2br (string) {
      return (string + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br />$2');
    }

    /**
     * Applies line limit if applicable with a timeout,
     * so that UI is rendered first
     *
     * @param {object} scope scope object
     * @param {object} elem element
     */
    function applyLineLimitIfApplicableWithTimeout (scope, elem) {
      $timeout(function () {
        applyLineLimitIfApplicable(scope, elem);
      });
    }

    /**
     * Applies line limit if applicable
     *
     * @param {object} scope scope object
     * @param {object} elem element
     */
    function applyLineLimitIfApplicable (scope, elem) {
      elem.siblings('.civicase__show-more-button').remove();
      elem.removeClass('civicase__show-more-block');

      if (scope.lineLimit) {
        unTruncateBlock(scope, elem);
        elem.addClass('civicase__show-more-block');

        var LINE_HEIGHT = parseInt(elem.css('line-height'));
        var elementHeight = elem.height();
        var linesOfTextVisible = elementHeight / LINE_HEIGHT;

        if (linesOfTextVisible > scope.lineLimit) {
          var seeMoreElement = '<a class="civicase__show-more-button">See More</span>';
          $(elem).after(seeMoreElement);

          truncateBlock(scope, elem, LINE_HEIGHT);

          elem.siblings('.civicase__show-more-button').click(function () {
            if ($(this).text() === 'See More') {
              unTruncateBlock(scope, elem);
              $(this).text('Hide');
            } else {
              truncateBlock(scope, elem, LINE_HEIGHT);
              $(this).text('See More');
            }
          });
        }
      }
    }

    /**
     * Truncates the Block
     *
     * @param {object} scope scope object
     * @param {object} elem element
     * @param {object} lineHeight height of each line
     */
    function truncateBlock (scope, elem, lineHeight) {
      elem.css('max-height', (scope.lineLimit * lineHeight) + 'px');
      elem.css('overflow', 'hidden');
    }

    /**
     * Untruncates the Block
     *
     * @param {object} scope scope object
     * @param {object} elem element
     */
    function unTruncateBlock (scope, elem) {
      elem.css('max-height', 'initial');
      elem.css('overflow', 'auto');
    }
  });
})(angular, CRM.$, CRM._, CRM);
