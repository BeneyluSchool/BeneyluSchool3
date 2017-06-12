(function (angular) {
'use strict';

angular.module('bns.components.overflow', [])

  .directive('bnsOverflow', BNSOverflowDirective)
  .directive('bnsOverflowItem', BNSOverflowItemDirective)
  .controller('BNSOverflowController', BNSOverflowController)

;

function BNSOverflowDirective () {

  return {
    restrict: 'EA',
    require: 'bnsOverflow',
    scope: true,
    controller: 'BNSOverflowController',
    controllerAs: 'overflow',
    bindToController: true,
    compile: function (element) {
      // element.attr({ 'layout': 'row', 'alyout-align': 'start center' });
      element.children().attr('bns-overflow-item', '');

      // build a menu to store overflowed items
      element.append('<md-menu md-position-mode="target-right target" ng-show="overflow.hiddenCount" class="bns-overflow-revealer">'+
        '<md-button class="md-icon-button" ng-click="$mdOpenMenu()">' +
          '<md-icon md-menu-origin> more_vert </md-icon>' +
        '</md-button>' +
        '<md-menu-content class="bns-overflow-destination"></md-menu-content>' +
      '</md-menu>');
    },
  };

}

function BNSOverflowItemDirective () {

  return {
    restrict: 'EA',
    require: '^^bnsOverflow',
    compile: function (element) {
      element.removeAttr('bns-overflow-item');
      var elementTemplate = element[0].outerHTML; // store raw template before compilation

      return function postLink (scope, element, attrs, overflowCtrl) {
        overflowCtrl.register(element, elementTemplate);
      };
    },
  };

}

function BNSOverflowController ($scope, $element, $attrs, $window, $compile) {
  var ctrl = this;
  ctrl.register = register;
  ctrl.hiddenCount = 0;

  var children = [];
  var $destination = $element.find('.bns-overflow-destination');

  init();

  function init () {
    angular.element($window).on('resize', refresh);
    angular.element($window).on('orientationchange', refresh);

    $scope.$on('$destroy', cleanup);
  }

  function cleanup () {
    angular.element($window).off('resize', refresh);
    angular.element($window).off('orientationchange', refresh);
  }

  function register (child, template) {
    children.push(child);
    buildDestinationItemFromTemplate(template);
    refresh();
  }

  function refresh () {
    var bounds = $element[0].getBoundingClientRect();
    var tolerance = 56;       // account for the menu trigger
    var hiddenFromIdx = -1;   // index from which items are hidden

    ctrl.hiddenCount = 0;

    // in src, hide items that... overflow
    children.forEach(function ($child, idx) {
      $child.css('visibility', 'visible');
      if (bounds.right - $child[0].getBoundingClientRect().right < tolerance) {
        ctrl.hiddenCount++;
        $child.css('visibility', 'hidden');
        if (hiddenFromIdx < 0) {
          hiddenFromIdx = idx;
        }
      }
    });

    // in dest, hide items that are shown in src
    if (hiddenFromIdx >= 0) {
      $destination.children().show();
      $destination.children(':lt('+hiddenFromIdx+')').hide();
    } else {
      $destination.children().hide();
    }
  }

  function buildDestinationItemFromTemplate (template) {
    var item = angular.element(template).wrap('<md-menu-item></md-menu-item>').parent();
    $destination.append($compile(item)($scope));
  }

}

}) (angular);
