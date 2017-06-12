'use strict';

angular.module('bns.userDirectory.selectionController', [
  'bns.userDirectory.state',
])

.controller('UserDirectorySelectionController', function (userDirectoryState) {
  var ctrl = this;
  ctrl.selection = userDirectoryState.selection;
  ctrl.selectionGroup = userDirectoryState.selectionGroup;
  ctrl.selectionDistribution = userDirectoryState.selectionDistribution;
  ctrl.selectionRole = userDirectoryState.selectionRole;
  ctrl.locked = userDirectoryState.locked;
  ctrl.lockedGroup = userDirectoryState.lockedGroup;
  ctrl.lockedDistribution = userDirectoryState.lockedDistribution;
  ctrl.lockedRole = userDirectoryState.lockedRole;
});
