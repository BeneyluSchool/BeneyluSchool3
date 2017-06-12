'use strict';

angular.module('bns.core.navigationTree.navigationTreeNode', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.navigationTree.navigationTreeNode.bnsNavigationTreeNode
   * @kind function
   *
   * @description
   * Simple directive to create childscopes on navigation tree roots.
   *
   * @example
   * <any bns-navigation-tree-node></any>
   *
   * @returns {Object} The bnsNavigationTreeNode directive
   */
  .directive('bnsNavigationTreeNode', function (url) {
    return {
      templateUrl: url.view('core/navigation-tree/directives/bns-navigation-tree-node.html'),
      scope: {
        node: '=',
        key: '=',
      },
      controller: 'NavigationTreeNodeController',
      controllerAs: 'ctrl',
      bindToController: true,
      require: ['^bnsNavigationTreeRoot', 'bnsNavigationTreeNode'],
      link: function (scope, element, attrs, controllers) {
        var rootCtrl = controllers[0];
        var nodeCtrl = controllers[1];

        nodeCtrl.bind(rootCtrl);
      }
    };
  })

  .controller('NavigationTreeNodeController', function () {
    var ctrl = this;
    ctrl.bind = bind;
    ctrl.toggleExpanded = toggleExpanded;
    ctrl.isExpanded = false;
    ctrl.iconClasses = {};
    ctrl.isActive = isActive;
    ctrl.onClick = onClick;
    ctrl.busy = false;
    ctrl.childrenLoaded = true;

    /**************************************************************************\
     *    API
    \**************************************************************************/

    function bind (rootCtrl) {
      ctrl.tree = rootCtrl.tree;
      ctrl.conf = rootCtrl.conf;
      ctrl.children = ctrl.tree.getChildren(ctrl.node);

      if (ctrl.conf.loadChildren && ctrl.node.type !== 'TEAM') {
        ctrl.loadChildren = loadChildren;
        ctrl.childrenLoaded = false;
      }

      if (angular.isArray(ctrl.children)) {
        ctrl.childrenLoaded = true;
      }

      refresh();
    }

    function toggleExpanded () {
      ctrl.isExpanded = ctrl.tree.toggleExpanded(ctrl.node, ctrl.key);
    }

    function onClick () {
      (ctrl.conf.onClick || angular.noop)(ctrl.node);
      refresh();
    }

    function isActive () {
      return (ctrl.conf.isActive || angular.noop)(ctrl.node);
    }

    function loadChildren () {
      if (ctrl.childrenLoaded) {
        return;
      }

      if (!ctrl.conf.loadChildren) {
        return;
      }

      ctrl.busy = true;

      return ctrl.conf.loadChildren(ctrl.node)
        .then(success)
        .finally(end)
      ;
      function success (groups) {
        ctrl.children = ctrl.tree.setChildren(ctrl.node, groups);
        ctrl.childrenLoaded = true;

        if (!ctrl.children.length && ctrl.conf.emptyChildrenMessage) {
          ctrl.emptyChildrenMessage = ctrl.conf.emptyChildrenMessage;
        }

        toggleExpanded();
      }
      function end () {
        ctrl.busy = false;
      }
    }


    /**************************************************************************\
     *    Internals
    \**************************************************************************/

    function refresh () {
      ctrl.isExpanded = ctrl.tree.isExpanded(ctrl.node, ctrl.key);

      if (ctrl.conf.iconClasses) {
        if (angular.isFunction(ctrl.conf.iconClasses)) {
          ctrl.iconClasses = ctrl.conf.iconClasses(ctrl.node);
        } else {
          ctrl.iconClasses = ctrl.conf.iconClasses;
        }
      }
    }

  })

;
