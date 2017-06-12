'use strict';

angular.module('bns.userDirectory.sceneController', [
  'ui.router',
  'bns.userDirectory.groups',
  'bns.userDirectory.state',
])

.controller('UserDirectorySceneController', function (_, $window, $timeout, $scope, $state, $stateParams, userDirectory, userDirectoryGroups, userDirectoryUsers, userDirectoryState, orderByUserTypeFilter, USER_DIRECTORY_PROFILE_LIMIT) {
  var ctrl = this;
  ctrl.selectionGroup = userDirectoryState.selectionGroup;
  ctrl.toggleContextSelection = toggleContextSelection;
  ctrl.isContextSelected = isContextSelected;
  ctrl.viewProfile = viewProfile;
  ctrl.navigate = navigate;
  ctrl.locked = userDirectoryState.locked;
  ctrl.lockedGroup = userDirectoryState.lockedGroup;
  ctrl.userTypeOrder = userTypeOrder;
  ctrl.userTypes = [];
  ctrl.isEmpty = isEmpty;
  ctrl.busy = false;

  init();

  function init () {
    ctrl.busy = true;
    userDirectoryGroups.get($stateParams.id).then(function success (group) {
      // users are not visible individually, do not try to preload them
      if (!group.view_users) {
        return setupGroup();
      }

      // preload the first batch of users for each role
      var userIds = [];
      angular.forEach(group._embedded.users, function (users) {
        userIds = userIds.concat(users.slice(0, USER_DIRECTORY_PROFILE_LIMIT));
      });

      return userDirectoryUsers.lookup(_.uniq(userIds), userDirectoryState.view, group)
        .then(setupGroup);

      function setupGroup () {
        userDirectoryState.context = ctrl.group = group;
        ctrl.canSelectContext = userDirectoryState.allowGroupSelection && !(userDirectoryState.lockedGroup && _.contains(userDirectoryState.lockedGroup, userDirectoryState.context.id));
      }
    })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
    $scope.$watch('ctrl.group._embedded.users', function () {
      if (!(ctrl.group && ctrl.group._embedded)) {
        return;
      }

      ctrl.userTypes = orderByUserTypeFilter(ctrl.group._embedded.users);
    }, true);
  }

  function toggleContextSelection () {
    if (userDirectoryState.context) {
      userDirectoryState.selectionGroup.toggle(userDirectoryState.context);
    }
  }

  function isContextSelected () {
    return userDirectoryState.context && userDirectoryState.selectionGroup.has(userDirectoryState.context);
  }

  function viewProfile (user) {
    if (!userDirectory.isSelection() && user._links && user._links.profile && user._links.profile.href && 'parent' !== user.main_role) {
      $window.location = user._links.profile.href;

      return false; // prevent selection
    }
  }

  function isEmpty () {
    if (!(ctrl.group && ctrl.group._embedded)) {
      return true;
    }
    if (ctrl.group._embedded.subgroups) {
      return !ctrl.group._embedded.subgroups.length;
    }

    var empty = true;
    angular.forEach(ctrl.group._embedded.users, function (users) {
      if (users.length) {
        empty = false;
      }
    });

    return empty;
  }

  function navigate (group) {
    $state.go('userDirectory.base.group', { id: group.id });

    return false; // prevent selection
  }

  function userTypeOrder (type, users) {
    console.log('userTypeOrder', type, users);
  }
})

.filter('orderByUserType', function () {
  return function (types) {
    var order = ['PUPIL', 'TEACHER', 'PARENT',  'DIRECTOR', 'ADMIN'];
    var result = [];

    for (var type in types) {
      if (types.hasOwnProperty(type)) {
        result.push({type: type, ids: types[type]});
      }
    }

    result.sort(function (a, b) {
      var idxA = order.indexOf(a.type);
      if (idxA === -1) {
        idxA = 8000;
      }

      var idxB = order.indexOf(b.type);
      if (idxB === -1) {
        idxB = 8000;
      }

      return idxA - idxB;
    });

    return result;
  };
})

;
