'use strict';

angular.module('bns.userDirectory.group.navigationController', [
  'bns.userDirectory.users',
])

.controller('UserDirectoryGroupNavigationController', function (group, userDirectoryUsers) {
  var ctrl = this;
  ctrl.group = group;
  ctrl.teachers = [];
  ctrl.workspaces = ctrl.group._embedded.subgroups;

  init();

  function init () {
    if (['CLASSROOM', 'TEAM'].indexOf(ctrl.group.type) > -1) {
      return;
    }

    return userDirectoryUsers.lookup(ctrl.group._embedded.users.TEACHER)
      .then(function success (users) {
        ctrl.teachers = users;
      })
    ;
  }
});
