(function (angular) {
'use strict'  ;

angular.module('bns.main.apps')

  .directive('bnsAppUninstall', BNSAppUninstallDirective)
  .controller('BNSAppUninstallController', BNSAppUninstallController)

;

/**
 * @ngdoc directive
 * @name bnsAppUninstall
 * @module bns.main.apps
 *
 * @description
 * Toggle open/close the given app
 *
 * ** Attributes **
 *  - `app` {Object} (required): The app to uninstall
 *  - `group` {Object} (required): The group where to uninstall. Required if no
 *                                 group id given
 *  - `groupId` {Integer} (required): ID of the group where to uninstall.
 *                                    Required if no group given.
 *  - `notify` {Boolean}: Whether to notify user of success (or failure) of the
 *                        uninstallation. Defaults to false.
 */
function BNSAppUninstallDirective () {

  return {
    scope: {
      app: '=*',
      group: '=*',
      groupId: '=',
      notify: '=',
    },
    template: '<md-button ng-if="ctrl.app && ctrl.app.is_uninstallable" '+
      'ng-model="ctrl.status" '+
      'ng-click="ctrl.uninstall($event)" '+
      'ng-disabled="ctrl.busy" '+
      'class="md-warn md-raised btn-hover btn-sm" '+
      'ng-bind-html="\'APPS.BUTTON_UNINSTALL\' | translate | buttonize" ' +
      '>.</md-button>',
    controller: 'BNSAppUninstallController',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSAppUninstallController ($scope, dialog, toast, appsManager) {

  var ctrl = this;
  ctrl.uninstall = uninstall;
  ctrl.busy = false;

  function uninstall ($event) {
    return dialog.confirm({
      title: 'APPS.TITLE_UNINSTALL_CONFIRM',
      content: 'APPS.DESCRIPTION_UNINSTALL_CONFIRM',
      ok: 'APPS.BUTTON_UNINSTALL_CONFIRM',
      cancel: 'APPS.BUTTON_UNINSTALL_CANCEL',
      intent: 'warn',
      targetEvent: $event,
    })
      .then(doUninstall)
    ;
  }

  function doUninstall () {
    var groupId = ctrl.group ? ctrl.group.id : ctrl.groupId;
    ctrl.busy = true;

    return appsManager.uninstall(ctrl.app, groupId)
      .then(success)
      .catch(error)
      .finally(end)
    ;

    function success () {
      ctrl.app.is_open = !ctrl.app.is_open;
      $scope.$emit('bns.app.uninstall', ctrl.app);
      if (ctrl.notify) {
        toast.success('APPS.FLASH_APP_UNINSTALL_SUCCESS');
      }
    }
    function error () {
      if (ctrl.notify) {
        toast.error('APPS.FLASH_APP_UNINSTALL_ERROR');
      }
    }
    function end () {
      ctrl.busy = false;
    }
  }

}

})(angular);
