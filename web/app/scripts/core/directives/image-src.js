/* global Image */
(function (angular, Image) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.imageSrc
 *
 * @description
 * Various features for image loading and fallback
 */
angular.module('bns.core.imageSrc', [])

  .directive('bnsFallbackSrc', BNSFallbackSrcDirective)
  .directive('bnsLoadingSrc', BNSLoadingSrcDirective)

;

/**
 * @ngdoc directive
 * @name bnsFallbackSrc
 * @module bns.core.imageSrc
 *
 * @description
 * Displays the given fallback when attached image fails to load. Defaults to a
 * transparent pixel.
 *
 * @example
 * <!-- default transparent fallback -->
 * <img ng-src="{{ myImgUrl }}" bns-fallback-src>
 *
 * <!-- custom image fallback -->
 * <img ng-src="{{ myImgUrl }}" bns-fallback-src="{{ myFallbackImgSrc }}">
 */
function BNSFallbackSrcDirective () {

  // 1px transparent gif
  var DEFAULT_FALLBACK_SRC = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  return {
    restrict: 'A',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    // Listen for errors on the element and if there are any replace the source
    // with the fallback source
    element.on('error', errorHanlder);

    function errorHanlder () {
      element.off('error', errorHanlder);
      var newSrc = attrs.bnsFallbackSrc || DEFAULT_FALLBACK_SRC;
      if (element[0].src !== newSrc) {
        element[0].src = newSrc;
      }
    }
  }

}

function BNSLoadingSrcDirective () {

  var DEFAULT_LOADING_SRC = '';

  return {
    restrict: 'A',
    compile: function (element, attrs) {
      attrs.imgSrc = attrs.ngSrc;
      delete attrs.ngSrc;

      return postLink;
    }
  };

  function postLink (scope, element, attrs) {
    element[0].src = attrs.bnsLoadingSrc || DEFAULT_LOADING_SRC;

    var img = new Image();
    img.src = attrs.imgSrc;
    img.onload = function () {
      img.onload = null;
      if (element[0].src !== img.src) {
        element[0].src = img.src;
      }
    };
  }

}

}) (angular, Image);
