'use strict';

angular.module('bns.userDirectory.group.moduleIcon', [
  'bns.core.url',
])

  .directive('bnsUserDirectoryGroupModuleIcon', function (url) {
    return {
      replace: true,
      templateUrl: url.view('user-directory/group/directives/module-icon.html'),
      scope: {
        module: '=bnsUserDirectoryGroupModuleIcon',
      },
      controller: 'UserDirectoryGroupModuleIconController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryGroupModuleIconController', function (url) {
    var ctrl = this;

    ctrl.iconPath = url.media('images/icons/modules/' + ctrl.module.unique_name.toLowerCase() + '/big.png');
  })
;
