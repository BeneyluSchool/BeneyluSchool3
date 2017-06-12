(function (angular) {
'use strict';

/**
 * @ngdoc constant
 * @name BNS_STATUS
 * @description BNS status
 *
 * @example
 * BNS_STATUS.PUBLISHED; // 'published'
 */
var BNS_STATUS = {};

/**
 * @ngdoc constant
 * @name BNS_STATUS_VARIANTS
 * @description BNS status variants
 *
 * @example
 * BNS_STATUS_VARIANTS.PUBLISHED; // [ 'published', 'PUBLISHED', 'pub', 'PUB' ]
 */
var BNS_STATUS_VARIANTS = {};

// 'status name': [array of possible variants]
var statusMap = {
  'published':  [1, 'pub'],
  'scheduled':  ['sched', 'programmed'],
  'correction': [3, 'corr', 'waiting_for_correction'],
  'finished':   [2, 'fin'],
  'draft':      [0],
};

// build the map of status and variants
angular.forEach(statusMap, function (variantsSource, status) {
  var key = status.toUpperCase();
  // verbatim status name + uppercase version
  var variants = [status, key];

  // additional variants, and their uppercase version
  angular.forEach(variantsSource, function (variant) {
    variants.push(variant);
    if (angular.isString(variant)) {
      variants.push(variant.toUpperCase());
    }
  });

  BNS_STATUS[key] = status;
  BNS_STATUS_VARIANTS[key] = variants;
});

angular.module('bns.core.status', [])

  .constant('BNS_STATUS', BNS_STATUS)
  .constant('BNS_STATUS_VARIANTS', BNS_STATUS_VARIANTS)
  .factory('bnsStatus', BNSStatusFactory)

;

function BNSStatusFactory (BNS_STATUS, BNS_STATUS_VARIANTS) {

  return {
    guess: guess,
  };

  /**
   * Guesses the status value of the given object, by looking at the given
   * property (defaults to `value`).
   *
   * @param {Object} obj
   * @param {String} property
   * @returns {String} The normalized status name
   */
  function guess (obj, property) {
    property = property || 'value';

    var found = null;
    for (var status in BNS_STATUS) {
      if (status === obj[property]) {
        found = status;
        break;
      }

      if (BNS_STATUS_VARIANTS[status].indexOf(obj[property]) > -1) {
        found = status;
        break;
      }
    }

    return found ? found.toLowerCase() : null;
  }

}

})(angular);
