'use strict';

angular.module('bns.userDirectory.group.sceneController', [
  'pascalprecht.translate',
  'bns.userDirectory.group.modules',
])

.controller('UserDirectoryGroupSceneController', function (group, $translate, userDirectoryGroupModules) {
  var ctrl = this;
  ctrl.group = group;
  ctrl.getModuleOrderKey = getModuleOrderKey;
  ctrl.busy = false;

  init();

  function init () {
    ctrl.busy = true;

    return userDirectoryGroupModules.get(ctrl.group)
      .then(function success (modules) {
        ctrl.modules = modules;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function getModuleOrderKey (module) {
    return $translate.instant('USER_DIRECTORY.GROUP.' + module.unique_name);
  }
});
