'use strict';

angular.module('bns.userDirectory.navigationController', [
  'ui.router',
  'bns.core.navigationTree.service',
  'bns.userDirectory.state',
  'bns.userDirectory.groups',
])

.controller('UserDirectoryNavigationController', function (_, $state, $q, $rootScope, $scope, $translate, NavigationTree, Users, userDirectoryState, userDirectoryGroups, UserDirectoryRestangular) {
  var ctrl = this;

  ctrl.selectionGroup = userDirectoryState.selectionGroup;
  ctrl.state = userDirectoryState;

  init();

  function init () {
    userDirectoryGroups.getList().then(function (groups) {
      ctrl.groups = groups;
      ctrl.groupsTree = new NavigationTree(ctrl.groups, 'id', '_embedded.subgroups');
      ctrl.treeConfiguration = {
        onClick: function (node) {
          $state.go('userDirectory.base.group', { id: node.id, type: null });
        },
        isActive: function (node) {
          return (!$state.params.type || $state.params.type === 'user') && userDirectoryState.context && ctrl.groupsTree.equality(userDirectoryState.context, node);
        },
        iconClasses: function (node) {
          return ['ud-icon', node.type.toLowerCase()];
        },
        loadChildren: function (group) {
          return UserDirectoryRestangular.one('groups', group.id).all('subgroups').getList({
            view: userDirectoryState.view,
          });
        },
        emptyChildrenMessage: $translate.instant('USER_DIRECTORY.LABEL_NO_SUBGROUPS'),
      };

      return Users.hasRight('CAMPAIGN_ACCESS').then(setupDistributionTree);
    });

    // expand parent groups when context changes
    $scope.$watch(function () {
      return userDirectoryState.context;
    }, function (group) {
      if (!group) {
        return;
      }
      if (ctrl.groupsTree && (!$state.params.type || $state.params.type === 'user')) {
        ctrl.groupsTree.expandAncestors(group);
      } else if (ctrl.distributionGroupsTree && $state.params.type === 'distribution') {
        ctrl.distributionGroupsTree.expandAncestors(group);
      }
    });

    var unlistenCreated = $rootScope.$on('userDirectory.group.created', function (event, group) {
      ctrl.groups.push(group);
    });
    var unlistenRemoved = $rootScope.$on('userDirectory.group.removed', function (event, group) {
      _.remove(ctrl.groups, function (g) {
        return g.id === group.id;
      });
    });
    $scope.$on('$destroy', function cleanup () {
      unlistenCreated();
      unlistenRemoved();
    });
  }

  function setupDistributionTree () {
    ctrl.distributionGroupsTree = new NavigationTree({
      id: 0,
      label: $translate.instant('USER_DIRECTORY.LABEL_MY_DISTRIBUTION_LISTS'),
      _embedded: {
        subgroups: ctrl.groups,
      },
    }, 'id', '_embedded.subgroups');
    ctrl.distributionTreeConfiguration = {
      onClick: function (node) {
        if (!node.id) {
          return;
        }

        $state.go('userDirectory.base.group', { id: node.id, type: 'distribution' });
      },
      isActive: function (node) {
        return $state.params.type === 'distribution' && ctrl.groupsTree.equality(userDirectoryState.context, node);
      },
      iconClasses: function (node) {
        return ['ud-icon', node.type ? node.type.toLowerCase() : 'distribution-list'];
      },
      loadChildren: function (group) {
        // todo get only groups where CAMPAIGN_ACCESS
        if (!group.id) {
          return $q.resolve(ctrl.groups);
        }

        return UserDirectoryRestangular.one('groups', group.id).all('subgroups').getList();
      },
    };
  }
});
