(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.tabs
 *
 * @description
 * Simple tabs. NOT related to md-tabs
 */
angular.module('bns.components.tabs', [])

  .directive('bnsTabs', BNSTabsDirective)
  .controller('BNSTabsController', BNSTabsController)
  .directive('bnsTab', BNSTabDirective)

;

/**
 * @ngdoc directive
 * @name bnsTabs
 *
 * @description
 * Parent directive for tabs. Specify a two-way bound expression to track
 * current tab. On child tabs, specify a string to identify the tab. Will be
 * assigned to the expression, and tab will be marked as current.
 *
 *
 * @example
 * <any bns-tabs="my.current.tab">
 *   <any bns-tab="tab1" />
 *   <any bns-tab="tab2" />
 * </any>
 */
function BNSTabsDirective () {

  return {
    scope: {
      current: '=bnsTabs',
    },
    controller: 'BNSTabsController',
    controllerAs: 'tabs',
    bindToController: true,
  };

}

function BNSTabsController ($scope, $element) {

  var children = $element.children();

  $scope.$watch('tabs.current', updateCurrentChild);

  function updateCurrentChild (current) {
    children.removeClass('current');
    angular.forEach(children, function (tab) {
      tab = angular.element(tab);
      if (tab.attr('bns-tab') === current) {
        tab.addClass('current');
      }
    });
  }

}

function BNSTabDirective () {

  return {
    require: '^bnsTabs',
    link: postLink,
  };

  function postLink (scope, element, attrs, ctrl) {
    var tabs = ctrl;

    element.on('click', setCurrentTab);
    scope.$on('$destroy', cleanup);

    function cleanup () {
      element.off('click', setCurrentTab);
    }

    function setCurrentTab () {
      tabs.current = attrs.bnsTab;
    }
  }

}

})(angular);
