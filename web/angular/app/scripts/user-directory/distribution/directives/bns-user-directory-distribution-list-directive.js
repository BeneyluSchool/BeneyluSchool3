'use strict';

angular

  .module('bns.userDirectory.distribution.bnsUserDirectoryDistributionList', [
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
  .directive('bnsUserDirectoryDistributionList', function (url) {
    return {
      templateUrl: url.view('user-directory/distribution/directives/bns-user-directory-distribution-list.html'),
      scope: {
        list: '=bnsUserDirectoryDistributionList',
        selection: '=',
        locked: '=',
        onClick: '=',
      },
      controller: 'UserDirectoryDistributionListController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryDistributionListController', function (_, url) {
    var ctrl = this;
    ctrl.handleClick = handleClick;
    ctrl.toggle = toggle;
    ctrl.isLocked = isLocked;
    ctrl.imageUrl = url.image('user-directory/distribution-list.png');

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
