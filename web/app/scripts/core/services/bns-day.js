(function (angular) {
'use strict';

/**
 * @ngdoc constant
 * @name BNS_DAY
 * @description BNS day
 *
 * @example
 * BNS_DAY.MONDAY; // 'monday'
 */
var BNS_DAY = {};

/**
 * @ngdoc constant
 * @name BNS_DAY_VARIANTS
 * @description BNS day variants
 *
 * @example
 * BNS_DAY_VARIANTS.PUBLISHED; // [ 'published', 'PUBLISHED', 'pub', 'PUB' ]
 */
var BNS_DAY_VARIANTS = {};

// 'days name': [array of possible variants]
var daysMap = {
  'monday':  [1, 'mo'],
  'tuesday':  [2, 'tu'],
  'wednesday': [3, 'we'],
  'thursday': [4, 'th'],
  'friday':  [5, 'fr'],
  'saturday': [6, 'sa'],
  'sunday': [7, 'su'],
};

// build the map of day and variants
angular.forEach(daysMap, function (variantsSource, day) {
  var key = day.toUpperCase();
  // verbatim day name + uppercase version
  var variants = [day, key];

  // additional variants, and their uppercase version
  angular.forEach(variantsSource, function (variant) {
    variants.push(variant);
    if (angular.isString(variant)) {
      variants.push(variant.toUpperCase());
    }
  });

  BNS_DAY[key] = day;
  BNS_DAY_VARIANTS[key] = variants;
});

angular.module('bns.core.day', [])

  .constant('BNS_DAY', BNS_DAY)
  .constant('BNS_DAY_VARIANTS', BNS_DAY_VARIANTS)
  .factory('bnsDay', BNSDayFactory)

;

function BNSDayFactory (BNS_DAY, BNS_DAY_VARIANTS) {

  return {
    guess: guess,
  };

  /**
   * Guesses the day value of the given object, by looking at the given
   * property (defaults to `value`).
   *
   * @param {Object} obj
   * @param {String} property
   * @returns {String} The normalized day name
   */
  function guess (obj, property) {
    property = property || 'value';

    var found = null;
    for (var day in BNS_DAY) {
      if (day === obj[property]) {
        found = day;
        break;
      }

      if (BNS_DAY_VARIANTS[day].indexOf(obj[property]) > -1) {
        found = day;
        break;
      }
    }

    return found ? found.toLowerCase() : null;
  }

}

})(angular);
