(function ($, _) {
  window.waitForElement = function ($, elementPath, callBack) {
    window.setTimeout(function () {
      if ($(elementPath).length) {
        callBack($, $(elementPath));
      } else {
        window.waitForElement($, elementPath, callBack);
      }
    }, 500);
  };

  $(document).one('crmLoad', function () {
    const entityRefCustomFields = CRM.vars.civicase.entityRefCustomFields ?? [];

    entityRefCustomFields.forEach(field => {
      /* eslint-disable no-undef */
      waitForElement($, `[name^=${field.name}_]`, function ($, elem) {
        $(`[name^=${field.name}_]`)
          .attr('placeholder', field.placeholder)
          .attr('disabled', false)
          .crmEntityRef({
            entity: field.entity,
            create: false
          });
      });
    });
  });
})(CRM.$, CRM._);
