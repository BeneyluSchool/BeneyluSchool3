(function (angular) {
'use strict';

angular.module('bns.main.featureFlags')

  .filter('featureFlag', FeatureFlagFilter)

;

function FeatureFlagFilter (featureFlags) {

  var cache = {};

  function featureFlagFilter (flag) {
    if (undefined === cache[flag]) {
      cache[flag] = featureFlags.get(flag)
        .then(function (flagValue) {
          cache[flag] = flagValue;
        })
      ;
    }

    if (cache[flag] && angular.isFunction(cache[flag].then)) {
      return undefined; // promise
    }

    return cache[flag];
  }
  featureFlagFilter.$stateful = true;

  return featureFlagFilter;

}

})(angular);
