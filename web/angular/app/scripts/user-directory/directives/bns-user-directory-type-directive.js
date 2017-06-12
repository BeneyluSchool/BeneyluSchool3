'use strict';

angular

  .module('bns.userDirectory.bnsUserDirectoryType', [
    'bns.core.url',
    'bns.core.arrayUtils',
    'bns.userDirectory.users',
    'bns.userDirectory.state',
  ])

  .directive('bnsUserDirectoryType', function (url) {
    return {
      templateUrl: url.view('user-directory/directives/bns-user-directory-type.html'),
      scope: {
        type: '=',
        ids: '=',
        locked: '=',
        onClick: '=',
        group: '=',
      },
      controller: 'UserDirectoryTypeController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryTypeController', function (_, $q, $scope, arrayUtils, userDirectoryUsers, userDirectoryState, USER_DIRECTORY_PROFILE_LIMIT) {
    var LIMIT = USER_DIRECTORY_PROFILE_LIMIT;
    var ctrl = this;
    ctrl.users = [];
    ctrl.index = 0;
    ctrl.selection = userDirectoryState.selection;
    ctrl.selectionRole = userDirectoryState.allowRoleSelection ? userDirectoryState.selectionRole : null;
    ctrl.role = {
      id: ''+ctrl.group.id+ctrl.type, // unique key for CollectionMap
      type: ctrl.type,                // api value
      group_id: ctrl.group.id,        // api value
      group: ctrl.group,              // for display
    };
    ctrl.loadMore = loadMore;
    ctrl.busy = false;
    ctrl.expanded = true;
    ctrl.isAllSelected = false;
    ctrl.toggleSelection = toggleSelection;

    init();

    function init () {
      // load first batch of users
      loadMore();
      $scope.$watchCollection('ctrl.selection.list', function () {
        ctrl.isAllSelected = checkAllSelected();
      });
    }

    /**
     * Loads the next batch of users
     *
     * @param  {Boolean} all Whether to load all users at once
     * @returns {Object} A promise
     */
    function loadMore (all) {
      if (!(ctrl.group && ctrl.group.view_users)) {
        return; // abort if users are not visible individually
      }

      var endIndex = all ? ctrl.ids.length : (ctrl.index + LIMIT);
      var ids = ctrl.ids.slice(ctrl.index, endIndex);

      if (ids.length) {
        ctrl.busy = true;
        return userDirectoryUsers.lookup(ids, userDirectoryState.view, ctrl.group)
          .then(success)
          .finally(end)
        ;
      } else {
        return $q.resolve(); // dummy promise, for consistent method signature
      }
      function success (users) {
        arrayUtils.merge(ctrl.users, users);
        ctrl.index += users.length;
      }
      function end () {
        ctrl.busy = false;
        ctrl.expanded = true;
      }
    }

    function toggleSelection () {
      if (ctrl.isAllSelected) {
        angular.forEach(ctrl.users, function (user) {
          ctrl.selection.remove(user);
        });
      } else {
        loadMore(true).then(function selectAll () {
          ctrl.selection.addc(ctrl.users);
        });
      }
    }

    /**
     * Checks if all users are selected
     *
     * @returns {Boolean}
     */
    function checkAllSelected () {
      return _.every(ctrl.ids, function (id) {
        return ctrl.selection.isset(id);
      });
    }
  })

;
