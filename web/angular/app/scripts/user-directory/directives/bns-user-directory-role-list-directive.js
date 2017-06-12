'use strict';

angular

  .module('bns.userDirectory.bnsUserDirectoryRoleList', [
    'bns.core.url',
  ])

  /**
   * @ngdoc directive
   * @name bns.userDirectory.bnsUserDirectoryGroupList
   * @kind function
   *
   * @description
   * Displays a list of roles in the user directory, linked to the given
   * selection
   *
   * @example
   * <any bns-user-directory-role-list="myArrayOfRoles" selection="myCollectionMap"></any>
   */
  .directive('bnsUserDirectoryRoleList', function (url) {
    return {
      templateUrl: url.view('user-directory/directives/bns-user-directory-role-list.html'),
      scope: {
        list: '=bnsUserDirectoryRoleList',
        selection: '=',
        locked: '=',
        onClick: '=',
      },
      controller: 'UserDirectoryRoleListController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryRoleListController', function (_) {
    var ctrl = this;
    ctrl.handleClick = handleClick;
    ctrl.toggle = toggle;
    ctrl.isLocked = isLocked;

    function handleClick (role) {
      return (ctrl.onClick || angular.noop)(role);
    }

    function isLocked (role) {
      return ctrl.locked && _.contains(ctrl.locked, role.id);
    }

    function toggle (role) {
      if (ctrl.isLocked(role)) {
        return false;
      }

      ctrl.selection.toggle(role);
    }
  })

;
