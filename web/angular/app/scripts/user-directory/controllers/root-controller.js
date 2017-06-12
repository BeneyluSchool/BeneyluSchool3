'use strict';

angular.module('bns.userDirectory.rootController', [
  'ui.router',
  'bns.main.statistic',
  'bns.userDirectory.groups',
  'bns.main.navbar',
])

.controller('UserDirectoryRootController', function ($state, navbar, userDirectory, userDirectoryState, userDirectoryGroups, statistic) {
  var ctrl = this;

  ctrl.deactivate = function () {
    return userDirectory.deactivate();
  };

  userDirectory.activate({
    view: '',
    group: navbar.group,
  });

  // asked for the default directory state: redirect to view of group
  if (!$state.includes('userDirectory.base')) {
    statistic.visit('user_directory');

    userDirectoryGroups.getList().then(function (groups) {
      // check that we have at least one group
      var defaultGroup = groups[0];
      if (!defaultGroup) {
        return console.warn('No group in user directory');
      }

      var groupId = null;
      if (userDirectoryState.intent) {
        groupId = userDirectoryState.intent.id || userDirectoryState.intent;
      }

      if (groupId) {
        // redirect to wanted group
        userDirectoryGroups.get(groupId)
          .then(function (group) {
            if (!group) {
              console.warn('Could not find group with id ', groupId);
              group = defaultGroup;
            }
            userDirectoryState.intent = null;
            $state.go('userDirectory.base.group', { id: group.id });
          })
        ;
      } else {
        // finally, redirect to the default group
        $state.go('userDirectory.base.group', { id: defaultGroup.id });
      }
    });
  }

});
