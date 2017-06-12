'use strict';

angular.module('bns.userDirectory', [
  // app-wide stuff
  // TODO: break into small pieces
  'bns.core',
  // configuration
  'bns.userDirectory.config.states',
  // directives
  'bns.userDirectory.bnsUserDirectoryInvoke',
  'bns.userDirectory.bnsUserDirectoryType',
  'bns.userDirectory.bnsUserDirectoryGroupList',
  'bns.userDirectory.bnsUserDirectoryUserList',
  'bns.userDirectory.bnsUserDirectoryRoleList',
  'bns.userDirectory.groupImage',
  // controllers
  'bns.userDirectory.rootController',
  'bns.userDirectory.topbarController',
  'bns.userDirectory.navigationController',
  'bns.userDirectory.selectionController',
  'bns.userDirectory.sceneController',
  // submodules
  'bns.userDirectory.distribution',
  'bns.userDirectory.groupModule',
])

  /**
   * @ngdoc constant
   * @name USER_DIRECTORY_PROFILE_LIMIT
   * @description
   * Number of user profiles (per role) displayed per batch.
   */
  .constant('USER_DIRECTORY_PROFILE_LIMIT', 30)

;
