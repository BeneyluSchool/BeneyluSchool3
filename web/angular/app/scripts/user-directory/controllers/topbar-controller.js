'use strict';

angular.module('bns.userDirectory.topbarController', [
  'bns.userDirectory.state',
  'bns.userDirectory.rights',
  'bns.userDirectory.addGroupModal',
  'bns.userDirectory.deleteGroupModal',
  'bns.userDirectory.editWorkspaceModal',
  'bns.userDirectory.addUsersModal',
  'bns.userDirectory.removeUsersModal',
])

.controller('UserDirectoryTopbarController', function (_, $rootScope, $scope, $window, Routing, userDirectory, userDirectoryState, userDirectoryRights, userDirectoryAddGroupModal, userDirectoryDeleteGroupModal, userDirectoryEditWorkspaceModal, userDirectoryAddUsersModal, userDirectoryRemoveUsersModal) {
  var ctrl = this;
  ctrl.selection = userDirectoryState.selection;
  ctrl.close = close;
  ctrl.isSelectionMode = userDirectory.isSelection;
  ctrl.validateSelection = validateSelection;
  ctrl.addGroup = addGroup;
  ctrl.deleteGroup = deleteGroup;
  ctrl.editWorkspace = editWorkspace;
  ctrl.addUsers = addUsers;
  ctrl.addUser = addUser;
  ctrl.removeUsers = removeUsers;
  ctrl.rights = {
    canAddGroup: false,
    canDeleteGroup: false,
    canEditWorkspace: false,
    canAddUsers: false,
    canRemoveUsers: false,
  };
  ctrl.state = userDirectoryState;
  ctrl.createDistributionListRequest = createDistributionListRequest;
  ctrl.deleteDistributionListsRequest = deleteDistributionListsRequest;

  init();

  function init () {
    $scope.$watch('ctrl.state.context', function () {
      checkContext();
    });
    $scope.$watchCollection('ctrl.state.selectionGroup.list', function () {
      checkContext();
    });
  }

  function checkContext () {
    if (userDirectoryState.context) {
      ctrl.rights.canAddGroup = userDirectoryRights.canAddGroup();
      ctrl.rights.canDeleteGroup = userDirectoryRights.canDeleteGroup();
      ctrl.rights.canEditWorkspace = userDirectoryRights.canEditWorkspace();
      ctrl.rights.canAddUsers = userDirectoryRights.canAddUsers();
      ctrl.rights.canRemoveUsers = userDirectoryRights.canRemoveUsers();
      ctrl.rights.canAddLists = userDirectoryRights.canAddLists();
    }
  }

  function close () {
    userDirectory.deactivate();
  }

  function validateSelection () {
    if (!ctrl.isSelectionMode()) {
      return false;
    }

    $rootScope.$emit('userDirectory.selection', userDirectoryState.selectionGroup, userDirectoryState.selection, userDirectoryState.selectionDistribution, userDirectoryState.selectionRole);
    if (userDirectoryState.onSelection) {
      userDirectoryState.onSelection(userDirectoryState.selectionGroup.list, userDirectoryState.selection.list, userDirectoryState.selectionDistribution.list, userDirectoryState.selectionRole.list);
    }
    close();
  }

  function addGroup (withSelection) {
    if (withSelection) {
      userDirectoryAddGroupModal.selection = ctrl.selection;
    }
    userDirectoryAddGroupModal.activate();
  }

  function deleteGroup () {
    userDirectoryDeleteGroupModal.activate();
  }

  function editWorkspace () {
    userDirectoryEditWorkspaceModal.activate();
  }

  function addUser () {
    var route;
    if (userDirectoryState.context.type === 'CLASSROOM') {
      route = Routing.generate('BNSAppClassroomBundle_back_classroom');
    }

    if (route) {
      $window.location.href = route;
    }
  }

  function addUsers () {
    userDirectoryAddUsersModal.activate();
  }

  function removeUsers () {
    userDirectoryRemoveUsersModal.activate();
  }

  function createDistributionListRequest (event) {
    $rootScope.$emit('userDirectory.distribution.createRequest', event);
  }

  function deleteDistributionListsRequest (event) {
    $rootScope.$emit('userDirectory.distribution.deleteRequest', event);
  }
});
