(function (_, angular) {
  var module = angular.module('civicase');

  module.service('civicaseTruncateText', civicaseTruncateText);

  /**
   * Truncate text to the given length
   *
   * @returns {Function} truncated text
   */
  function civicaseTruncateText () {
    return function (value, limit) {
      return value.substr(0, limit) + ' ...';
    };
  }
})(CRM._, angular);
