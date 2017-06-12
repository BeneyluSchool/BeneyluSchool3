'use strict';

angular.module('bns.core.focusRequest', [])

  /**
   * @ngdoc directive
   * @name bns.core.focusRequest.bnsFocusRequest
   * @kind function
   *
   * @description
   * This simple directive requests for user focus once going live.
   *
   * @returns {Object} The bnsFocusRequest directive.
   */
  .directive('bnsFocusRequest', function () {
    return {
      link: function (scope, element) {
        element.focus();
      }
    };
  });
