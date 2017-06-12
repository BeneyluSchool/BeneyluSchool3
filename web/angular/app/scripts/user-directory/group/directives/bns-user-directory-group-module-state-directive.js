'use strict';

angular.module('bns.userDirectory.group.moduleState', [
  'bns.core.url',
  'bns.userDirectory.group.modules',
])

  .directive('bnsUserDirectoryGroupModuleState', function (url) {
    return {
      templateUrl: url.view('user-directory/group/directives/module-state.html'),
      scope: {
        group: '=',
        module: '@',
        role: '@',
        state: '=',
      },
      controller: 'UserDirectoryGroupModuleStateController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryGroupModuleStateController', function ($element, userDirectoryGroupModules) {
    var ctrl = this;
    ctrl.busy = false;
    ctrl.toggle = toggle;

    function toggle () {
      if (undefined === ctrl.state) {
        return;
      }

      ctrl.busy = true;

      return userDirectoryGroupModules.toggle(ctrl.group, ctrl.module, ctrl.role)
        .then(function (module) {
          ctrl.state = module.state;
        })
        .finally(function end () {
          ctrl.busy = false;
        })
      ;
    }
  })

;
