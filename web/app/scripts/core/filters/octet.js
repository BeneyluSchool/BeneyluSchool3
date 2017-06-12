(function (angular) {
'use strict';

angular.module('bns.core.octet', [])

  .filter('octet', OctetFilter)

;

/**
 * @ngdoc filter
 * @name octet
 * @module bns.core.octet
 * @kind function
 *
 * @description
 * Rounds the input to a human-readable octet size, and appends the correct
 * unit.
 *
 * @returns {Function} The octet filter
 */
function OctetFilter () {
  var factor = 1024;
  var units = ['o', 'Ko', 'Mo', 'Go', 'To', 'Po'];
  var precision = 2;
  var rounder = Math.pow(10, precision);

  return function (size) {
    size = parseInt(size, 10);
    if (!angular.isNumber(size) || isNaN(size)) {
      return null;
    }

    var iter = 0;   // number of iterations
    var mult;       // buffer for conversion to next multiple

    // get size to the most readable multiple, ie € [1, 1024[
    while (iter < units.length - 1) {
      mult = size / factor;
      if (1.0 > mult) {
        break;
      }
      size = mult;
      iter++;
    }

    return Math.round(size * rounder, precision) / rounder + units[iter];
  };
}

})(angular);
