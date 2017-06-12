'use strict';

angular

  .module('bns.userDirectory.editWorkspaceModal', [
    'btford.modal',
    'bns.core.url',
    'bns.userDirectory.groups',
    'bns.userDirectory.state',
    'bns.userDirectory.rights',
  ])

  .factory('userDirectoryEditWorkspaceModal', function (btfModal, url) {
    return btfModal({
      controller: 'UserDirectoryEditWorkspaceController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/user-directory/modals/edit-workspace-modal.html'),
    });
  })

  .controller('UserDirectoryEditWorkspaceController', function (userDirectoryGroups, userDirectoryState, userDirectoryRights, userDirectoryEditWorkspaceModal) {
    var ctrl = this;
    ctrl.group = {};
    ctrl.context = userDirectoryState.context;
    ctrl.confirm = confirm;
    ctrl.closeModal = closeModal;
    ctrl.busy = false;

    init();

    function init () {
      if (!ctrl.context) {
        return;
      }

      ctrl.group.label = ctrl.context.label;
    }

    function confirm () {
      if (ctrl.busy) {
        return;
      }

      ctrl.busy = true;
      ctrl.group.error = '';

      if (!(ctrl.context && userDirectoryRights.canEditWorkspace())) {
        ctrl.group.error = 'USER_DIRECTORY.EDIT_WORKSPACE_ERROR_NO_CONTEXT';
        ctrl.closeModal();
        return;
      }

      userDirectoryGroups.update(ctrl.context, {
        label: ctrl.group.label,
      })
        .then(function success () {
          ctrl.closeModal();
        })
        .catch(function error (response) {
          ctrl.group.error = 'USER_DIRECTORY.EDIT_WORKSPACE_ERROR';
          console.error('[POST team]', response);
        })
        .finally(function end() {
          ctrl.busy = false;
        })
      ;
    }

    function closeModal () {
      userDirectoryEditWorkspaceModal.deactivate();
    }
  })

;
