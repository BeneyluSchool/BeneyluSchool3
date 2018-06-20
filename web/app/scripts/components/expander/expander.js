(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.expander
 *
 * @description
 * A simple expander (accordion).
 */
angular.module('bns.components.expander', [
  'bns.core.trackHeight',
])

  .directive('bnsExpander', BNSExpanderDirective)
  .controller('BNSExpanderController', BNSExpanderController)

;

/**
 * @ngdoc directive
 * @name bnsExpander
 * @module bns.components.expander
 *
 * @restrict EA
 *
 * @description
 * Simple expander, can be used to build accordions. Displays a toggle button on
 * top of the actual content, which is transcluded.
 *
 * ** Attributes **
 *  - `label`: label, displayed inside the button
 *  - `isOpen` (bool): whether the component starts open. Defaults to false
  *  - `model` (expression): if given, the expander state is sync'ed with this
 *                          value, and a checkbox or a switch is displayed as the toggle
 *                          control.
 *  - `showToggle` (bool) : Show a control to toggle the expander (button, checkbox, switch) (default: true)
 *  - `showSwitch` (bool) : if has a `model` and `showToggle` is true then this display a switch instead of a checkbox (default: false)
 *
 * @example
 * <!-- simple expander, starting opened -->
 * <bns-expander label="'My Title'" is-open="true">
 *   Some cool content ...
 * <bns-expander>
 *
 * <!-- expander with a checkbox -->
 * <bns-expander label="'My Title'" model="myModelValue">
 *   Some cool content ...
 * <bns-expander>
 *
 * <!-- expander with checkbox and dummy model, starting opened -->
 * <bns-expander label="'My Title'" model="true">
 *   Some cool content ...
 * <bns-expander>
 *
 * <!-- expander with a switch -->
 * <bns-expander label="'My Title'" model="myModelValue" show-switch="true">
 *   Some cool content ...
 * <bns-expander>
 */
function BNSExpanderDirective () {

  return {
    restrict: 'EA',
    scope: {
      label: '@',
      isOpen: '=?model',
      showToggle: '=?',
      startOpen: '=',
      showSwitch: '=?'
    },
    transclude: true,
    controller: 'BNSExpanderController',
    controllerAs: 'expander',
    bindToController: true,
    templateUrl: 'views/components/expander/bns-expander.html',
  };

}

function BNSExpanderController ($scope, $element, $attrs, $timeout) {
  var expander = this;
  var $content = $element.find('.bns-expander-content');

  expander.hasModel = !!$attrs.model;
  expander.isOpen = !!(expander.isOpen || $attrs.isOpen || expander.startOpen);
  expander.toggle = toggle;
  expander.showSwitch = !!$attrs.showSwitch;

  init();

  function init () {
    // show toggle by default
    if (expander.showToggle !== false) {
      expander.showToggle = true;
    }

    // wait for transclude before watching
    $timeout(function () {
      $scope.$watch('expander.isOpen', function () {
        refreshHeight();
      });
    }, 0, false);

    // support textarea grow
    // TODO : deprecate this and only use forced event 'track.height'
    $element.on('keyup input', refreshHeight);
    $content.on('click', function() {
        $timeout(function() {
          refreshHeight();
        }, 0);
    });

    // support explicitly tracked height
    $scope.$on('track.height', refreshHeight);

    $scope.$on('$destroy', cleanup);

    // initial state
    if (!expander.isOpen) {
      $content.css('height', 0);
    }
    refreshClass();
  }

  function cleanup () {
    $element.off('keyup input', refreshHeight);
  }

  function toggle () {
    expander.isOpen = !expander.isOpen;
    refreshClass();
  }

  function refreshClass () {
    if (expander.isOpen) {
      $element.addClass('opened');
    } else {
      $element.removeClass('opened');
    }
  }

  function getHeights () {
    var targetHeight;
    var currentHeight = $content.css('height');
    $content.addClass('no-transition');
    $content.css('height', '');
    targetHeight = $content.prop('clientHeight');
    $content.css('height', currentHeight);
    $content.removeClass('no-transition');

    return {target: (expander.isOpen ? targetHeight : 0), current: currentHeight};
  }

  function refreshHeight () {
    var heights = getHeights();
    if (expander.cancelTimeout) {
      $timeout.cancel(expander.cancelTimeout);
    }
    // reset height to current height to have transition to new height
    $content.addClass('no-transition');
    $content.css('height', heights.current);
    $content.removeClass('no-transition');
    $timeout(function () {
      $content.css('height', heights.target + 'px');
    }, 0, false);
    if (heights.target !== 0) {
      // cancel the size of the element after the transition time
      // to allow expander grow with content outside open/close transitions
      expander.cancelTimeout = $timeout(function () {
        $content.css('height', '');
      }, 600, false);
    }
  }
}

}) (angular);
