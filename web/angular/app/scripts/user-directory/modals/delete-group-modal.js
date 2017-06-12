'use strict';

angular

  .module('bns.userDirectory.deleteGroupModal', [
    'btford.modal',
    'ui.router',
    'bns.core.url',
    'bns.userDirectory.groups',
    'bns.userDirectory.state',
    'bns.userDirectory.rights',
  ])

  .factory('userDirectoryDeleteGroupModal', function (btfModal, url) {
    return btfModal({
      controller: 'UserDirectoryDeleteGroupController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/user-directory/modals/delete-group-modal.html'),
    });
  })

  .controller('UserDirectoryDeleteGroupController', function ($rootScope, $state, userDirectoryGroups, userDirectoryState, userDirectoryRights, userDirectoryDeleteGroupModal) {
    var ctrl = this;
    ctrl.deletion = {
      confirmed: false,
      error: '',
    };
    ctrl.context = userDirectoryState.context;
    ctrl.confirm = confirm;
    ctrl.closeModal = closeModal;
    ctrl.busy = false;

    function confirm () {
      if (ctrl.busy) {
        return;
      }

      ctrl.deletion.error = '';

      if (!userDirectoryRights.canDeleteGroup()) {
        ctrl.deletion.error = 'USER_DIRECTORY.DELETE_WORKGROUP_ERROR_NO_RIGHT';
        ctrl.closeModal();
        return;
      }

      if (!ctrl.deletion.confirmed) {
        return false;
      }

      ctrl.busy = true;
      userDirectoryGroups.remove(ctrl.context)
        .then(function success (group) {
          $rootScope.$emit('userDirectory.group.removed', group);
          ctrl.closeModal();
          $state.go('userDirectory.base.group', { id: group.parent_id });
        })
        .catch(function error (response) {
          ctrl.deletion.error = response;
        })
        .finally(function end () {
          ctrl.busy = false;
        })
      ;
    }

    function closeModal () {
      userDirectoryDeleteGroupModal.deactivate();
    }
  })

;
