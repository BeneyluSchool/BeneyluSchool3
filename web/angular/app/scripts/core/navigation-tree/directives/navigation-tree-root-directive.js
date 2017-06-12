'use strict';

angular.module('bns.core.navigationTree.navigationTreeRoot', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.navigationTree.navigationTreeRoot.bnsNavigationTreeRoot
   * @kind function
   *
   * @description
   * Simple directive to create childscopes on navigation tree roots.
   *
   * @example
   * <any bns-navigation-tree-root tree="myNavigationTree" template="'path/to/my/template'"></any>
   *
   * @returns {Object} The bnsNavigationTreeRoot directive
   */
  .directive('bnsNavigationTreeRoot', function (url) {
    return {
      templateUrl: url.view('core/navigation-tree/directives/bns-navigation-tree-root.html'),
      scope: {
        tree: '=',
        template: '=',
        childrenPath: '@',
        conf: '=bnsNavigationTreeRoot',
      },
      controller: 'NavigationTreeRootController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('NavigationTreeRootController', function () {
    var ctrl = this;
    ctrl.conf = ctrl.conf || {};
  })

;
