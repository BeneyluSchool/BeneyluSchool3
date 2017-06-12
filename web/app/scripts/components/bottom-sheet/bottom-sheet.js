(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.bottomSheet
 */
angular.module('bns.components.bottomSheet', [])

  .factory('bottomSheet', BottomSheetFactory)
  .controller('BottomSheetMenu', BottomSheetMenuController)
  .directive('mdBottomSheet', MDBottomSheetDirective)

;

/**
 * @ngdoc service
 * @name bottomSheet
 * @module bns.components.bottomSheet
 *
 * @description
 * A wrapper of the $mdBottomSheet service.
 *
 * @requires $mdBottomSheet
 */
function BottomSheetFactory ($mdBottomSheet) {

  var SHOWING_CLASS = 'bns-bottom-sheet-is-showing';

  return {
    show: show,
    hide: hide,
    cancel: cancel,
  };

  function show (options) {
    angular.element('html').addClass(SHOWING_CLASS);

    options = angular.extend({
      controller: 'BottomSheetMenu',
      controllerAs: 'menu',
    }, options);

    return $mdBottomSheet.show(options)
      .finally(function () {
        angular.element('html').removeClass(SHOWING_CLASS);
      })
    ;
  }

  function hide () {
    return $mdBottomSheet.hide.apply($mdBottomSheet, arguments);
  }

  function cancel () {
    return $mdBottomSheet.cancel.apply($mdBottomSheet, arguments);
  }

}

/**
 * @ngdoc controller
 * @name BottomSheetMenu
 * @module bns.components.bottomSheet
 *
 * @description
 * The default bottom sheet menu controller, exposing useful service aliases.
 *
 * @requires $state
 * @requires bottomSheet
 */
function BottomSheetMenuController ($state, bottomSheet) {

  var menu = this;
  menu.close = menu.hide = bottomSheet.hide;
  menu.cancel = bottomSheet.cancel;
  menu.go = $state.go;

}

/**
 * @ngdoc directive
 * @name mdBottomSheet
 * @module bns.components.bottomSheet
 *
 * @description
 * Augment the original md-bottom-sheet directive to allow for scrollbars
 */
function MDBottomSheetDirective () {

  return {
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    if (attrs.allowScroll && scope.$eval(attrs.allowScroll)) {
      var scrollables = element.find('md-content');
      scrollables.on('touchmove', stopEventPropagation);
      scope.$on('$destroy', function () {
        scrollables.off('touchmove', stopEventPropagation);
      });
    }

    function stopEventPropagation (event) {
      event.stopPropagation();
    }
  }

}

})(angular);
