'use strict';

angular

  .module('bns.userDirectory.addGroupModal', [
    'btford.modal',
    'ui.router',
    'bns.core.url',
    'bns.userDirectory.groups',
    'bns.userDirectory.state',
    'bns.userDirectory.rights',
  ])

  .factory('userDirectoryAddGroupModal', function (btfModal, url) {
    return btfModal({
      controller: 'UserDirectoryAddGroupController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/user-directory/modals/add-group-modal.html'),
    });
  })

  .controller('UserDirectoryAddGroupController', function ($rootScope, $state, userDirectoryGroups, userDirectoryState, userDirectoryRights, userDirectoryAddGroupModal) {
    var ctrl = this;
    ctrl.group = {};
    ctrl.context = userDirectoryState.context;
    ctrl.users = getUsers();
    ctrl.confirm = confirm;
    ctrl.closeModal = closeModal;
    ctrl.busy = false;

    function confirm () {
      if (ctrl.busy) {
        return;
      }

      ctrl.group.error = '';

      var canAdd = userDirectoryRights.canAddGroup();

      if (!(ctrl.context && canAdd)) {
        ctrl.group.error = 'USER_DIRECTORY.ADD_WORKGROUP_ERROR_NO_CONTEXT';
        ctrl.closeModal();
        return;
      }

      ctrl.busy = true;
      userDirectoryGroups.create({
        label: ctrl.group.label,
        parent: ctrl.context.id,
        users: ctrl.users,
      })
        .then(function success (group) {
          $rootScope.$emit('userDirectory.group.created', group);
          ctrl.closeModal();
          $state.go('userDirectory.base.group', { id: group.id });
        })
        .catch(function error (response) {
          ctrl.group.error = 'USER_DIRECTORY.ADD_WORKGROUP_ERROR';
          console.error('[POST team]', response);
        })
        .finally(function end () {
          ctrl.busy = false;
        })
      ;
    }

    function closeModal () {
      userDirectoryAddGroupModal.deactivate();
    }

    /**
     * Gets the eventual list of users to add.
     * Users are not taken from global state selection, but from modal selection
     *
     * @returns {Array}
     */
    function getUsers () {
      var users = [];

      if (userDirectoryAddGroupModal.selection) {
        // get actual user objects
        userDirectoryAddGroupModal.selection.batch(function (item) {
          if (item.full_name) {
            users.push(item);
          }
        });
      }

      return users;
    }
  })

;
