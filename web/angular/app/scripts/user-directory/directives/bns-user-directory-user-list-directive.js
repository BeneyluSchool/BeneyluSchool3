'use strict';

angular

  .module('bns.userDirectory.bnsUserDirectoryUserList', [
    'bns.core.url',
    'bns.user.avatar',
  ])

  /**
   * @ngdoc directive
   * @name bns.userDirectory.bnsUserDirectoryUserList
   * @kind function
   *
   * @description
   * Displays a list of users in the user directory, linked to the given
   * selection
   *
   * @example
   * <any bns-user-directory-user-list="myArrayOfUsers" selection="myCollectionMap"></any>
   */
  .directive('bnsUserDirectoryUserList', function (url) {
    return {
      templateUrl: url.view('user-directory/directives/bns-user-directory-user-list.html'),
      scope: {
        list: '=bnsUserDirectoryUserList',
        selection: '=',
        locked: '=',
        onClick: '=',
      },
      controller: 'UserDirectoryUserListController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryUserListController', function (_) {
    var ctrl = this;
    ctrl.handleClick = handleClick;
    ctrl.isLocked = isLocked;
    ctrl.toggle = toggle;

    function handleClick (user) {
      return (ctrl.onClick || angular.noop)(user);
    }

    function isLocked (user) {
      return ctrl.locked && _.contains(ctrl.locked, user.id);
    }

    function toggle (user) {
      if (ctrl.isLocked(user)) {
        return false;
      }

      ctrl.selection.toggle(user);
    }
  })

;
