(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.assetize
 */
angular.module('bns.core.assetize', [])

  .filter('assetize', AssetizeFilter)

;

/**
 * @ngdoc filter
 * @name assetize
 * @module bns.core.assetize
 *
 * @description
 * Makes the string suitable for assets, ie. lowercase and dash-separated.
 */
function AssetizeFilter () {

  return function (str) {
    return str
      .toLowerCase()
      .replace(new RegExp('[ _]', 'g'), '-')
    ;
  };

}

})(angular);
