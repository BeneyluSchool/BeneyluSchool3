'use strict';

angular

  .module('bns.userDirectory.bnsUserDirectoryGroupList', [
    'bns.core.url',
  ])

  /**
   * @ngdoc directive
   * @name bns.userDirectory.bnsUserDirectoryGroupList
   * @kind function
   *
   * @description
   * Displays a list of groups in the user directory, linked to the given
   * selection
   *
   * @example
   * <any bns-user-directory-group-list="myArrayOfGroups" selection="myCollectionMap"></any>
   */
  .directive('bnsUserDirectoryGroupList', function (url) {
    return {
      templateUrl: url.view('user-directory/directives/bns-user-directory-group-list.html'),
      scope: {
        list: '=bnsUserDirectoryGroupList',
        selection: '=',
        locked: '=',
        onClick: '=',
      },
      controller: 'UserDirectoryGroupListController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryGroupListController', function (_) {
    var ctrl = this;
    ctrl.handleClick = handleClick;
    ctrl.toggle = toggle;
    ctrl.isLocked = isLocked;

    function handleClick (group) {
      return (ctrl.onClick || angular.noop)(group);
    }

    function isLocked (group) {
      return ctrl.locked && _.contains(ctrl.locked, group.id);
    }

    function toggle (group) {
      if (ctrl.isLocked(group)) {
        return false;
      }

      ctrl.selection.toggle(group);
    }
  })

;
