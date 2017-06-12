(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.trackHeight
 */
angular.module('bns.core.trackHeight', [])

  .directive('bnsTrackHeight', BNSTrackHeightDirective)

;

/**
 * @ngdoc directive
 * @name bnsTrackHeight
 * @module bns.core.trackHeight
 *
 * @description
 * Watchs for changes in the element height and notifies parent scopes.
 * Optionally, the element child can be watched instead. Useful if parent has a
 * fixed (or no) height: scrollable, accordion, ...
 *
 * @example
 * <!-- watch changes in height on the element -->
 * <any bns-track-height> someStuffThatWillChangeInHeight </any>
 *
 * <!-- watch changes in height on the child element -->
 * <any bns-track-height=">"> <div>someStuffThatWillChangeInHeight</div> </any>
 */
function BNSTrackHeightDirective () {

  return {
    restrict: 'A',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var target = element;

    // custom notation to track child element instead
    if (attrs.bnsTrackHeight === '>') {
      target = element.children();
    }

    // watch for changes in the target height during digest cycles
    scope.$watch(function getHeight () {
      return target.prop('clientHeight');
    }, function notify (newHeight, oldHeight) {
      if (newHeight !== oldHeight) {
        // height has changed but element is now hidden: false positive
        if (!target.prop('offsetParent')) {
          return;
        }
        scope.$emit('track.height', newHeight, oldHeight);
      }
    });
  }

}

})(angular);
