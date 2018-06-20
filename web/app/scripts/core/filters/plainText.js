(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.plainText
 */
angular.module('bns.core.plainText', [])

  .filter('plainText', PlainTextFilter)

;

/**
 * @ngdoc filter
 * @name PlainText
 * @module bns.core.plainText
 *
 * @description
 * Filters html in the given input, to keep only plain text.
 */
function PlainTextFilter () {

  return function (text) {
    return angular.element(text).text();
  };

}

})(angular);
