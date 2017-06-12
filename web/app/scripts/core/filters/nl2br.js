(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.nl2br
 */
angular.module('bns.core.nl2br', [])

  .filter('nl2br', Nl2brFilter)

;

/**
 * @ngdoc filter
 * @name nl2br
 * @module bns.core.nl2br
 *
 * @description
 * Replaces newline characters into <br> tags.
 */
function Nl2brFilter () {

  return function (str) {
    return (str+'').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
  };

}

})(angular);
