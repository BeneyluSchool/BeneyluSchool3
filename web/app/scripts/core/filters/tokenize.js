(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.tokenize
 */
angular.module('bns.core.tokenize', [])

  .filter('tokenize', TokenizeFilter)

;

/**
 * @ngdoc filter
 * @name tokenize
 * @module bns.core.tokenize
 *
 * @description
 * Makes a token-suitable string, ie all caps and underscores.
 */
function TokenizeFilter () {

  return function (str) {
    return str
      .toUpperCase()
      .replace(new RegExp('[ -]', 'g'), '_')
    ;
  };

}

})(angular);
