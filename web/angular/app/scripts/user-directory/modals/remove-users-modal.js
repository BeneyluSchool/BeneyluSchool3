'use strict';

angular

  .module('bns.userDirectory.removeUsersModal', [
    'btford.modal',
    'bns.core.url',
    'bns.userDirectory.groups',
    'bns.userDirectory.state',
    'bns.userDirectory.rights',
  ])

  .factory('userDirectoryRemoveUsersModal', function (btfModal, url) {
    return btfModal({
      controller: 'UserDirectoryRemoveUsersController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/user-directory/modals/remove-users-modal.html'),
    });
  })

  .controller('UserDirectoryRemoveUsersController', function (_, userDirectoryGroups, userDirectoryState, userDirectoryRights, userDirectoryRemoveUsersModal) {
    var ctrl = this;
    ctrl.group = {};
    ctrl.users = [];
    ctrl.context = userDirectoryState.context;
    ctrl.confirm = confirm;
    ctrl.closeModal = closeModal;
    ctrl.busy = false;

    init();

    function init () {
      if (!ctrl.context) {
        return;
      }

      ctrl.users = getUsers();
    }

    function confirm () {
      if (ctrl.busy) {
        return false;
      }

      ctrl.busy = true;
      ctrl.group.error = '';

      if (!(ctrl.context && userDirectoryRights.canEditWorkspace())) {
        ctrl.group.error = 'USER_DIRECTORY.REMOVE_USERS_ERROR_NO_CONTEXT';
        ctrl.closeModal();
        return;
      }

      userDirectoryGroups.removeUsers(ctrl.context, ctrl.users)
        .then(function success () {
          ctrl.closeModal();
        })
        .catch(function error () {
          ctrl.group.error = 'USER_DIRECTORY.REMOVE_USERS_ERROR';
        })
        .finally(function end() {
          ctrl.busy = false;
        })
      ;
    }

    function closeModal () {
      userDirectoryRemoveUsersModal.deactivate();
    }

    function getUsers () {
      // keep only users that are actually in the group
      return _.filter(userDirectoryState.selection.list, function (user) {
        return userDirectoryGroups.hasUser(ctrl.context, user);
      });
    }
  })

;
