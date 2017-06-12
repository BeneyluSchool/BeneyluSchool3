(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.search.google
 */
angular.module('bns.search.google', [
  'bns.core.loader',
])
  .factory('GoogleSearch', GoogleSearchFactory)

;

/**
 * @ngdoc service
 * @name GoogleSearch
 * @module bns.search.google
 *
 * @description
 * Lazy-loads the google custom search framework and exposes it, aka the
 * `google.search` global variable.
 *
 * @requires $window
 * @requires $q
 * @requires Loader
 */
function GoogleSearchFactory ($window, $q, Loader) {

  var loader = new Loader();

  var GoogleSearch = {
    _loaded: false,
    load: load,
  };

  return GoogleSearch;

  function load (cseCode) {
    return $q(googleSearchLoadPromise);

    function googleSearchLoadPromise (resolve, reject) {
      if (GoogleSearch._loaded) {
        // already loaded, resolve to the global directly
        resolve($window.google.search);
      } else {
        // inject the google api script
        var code = cseCode || '123:abcd';
        var url = 'https://cse.google.com/cse.js?cx=' + code;
        // init callback parameter
        $window.__gcse = {
          parsetags: 'explicit',
          callback: onGoogleSearchLoaded
        };
        loader.require([ url ]);
      }

      function onGoogleSearchLoaded () {
        // resolve to the now available global variable
        GoogleSearch._loaded = true;
        if ($window.google.search) {
          resolve($window.google.search);
        } else {
          // load failed
          reject();
        }

      }
    }
  }

}

})(angular);
