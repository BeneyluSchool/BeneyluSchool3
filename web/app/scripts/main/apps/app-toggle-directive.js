(function (angular) {
'use strict'  ;

angular.module('bns.main.apps')

  .directive('bnsAppToggle', BNSAppToggleDirective)
  .controller('BNSAppToggleController', BNSAppToggleController)

;

/**
 * @ngdoc directive
 * @name bnsAppToggle
 * @module bns.main.apps
 *
 * @description
 * Toggle open/close the given app
 *
 * ** Attributes **
 *  - `app` {Object} (required): The app to toggle
 *  - `group` {Object} (required): The group where to toggle. Required if no
 *                                 group id given
 *  - `groupId` {Integer} (required): ID of the group where to toggle. Required
 *                                    if no group given.
 *  - `notify` {Boolean|String}: Whether to notify user of success (or failure)
 *                               of the toggle. Defaults to false.
 *                               Can also be set to 'success' or 'error' to
 *                               notify only upon certain cases.
 *  - `type` {String}: The type of app to toggle: 'applications', 'activities'
 */
function BNSAppToggleDirective () {

  return {
    transclude: true,
    scope: {
      app: '=*',
      appName: '@',
      group: '=*',
      groupId: '=',
      userRole: '=',
      notify: '=',
      type: '=',
    },
    template: '<md-switch ng-if="ctrl.app" '+
      'ng-disabled="ctrl.busy" '+
      'ng-model="ctrl.status" '+
      'ng-change="ctrl.toggle()" '+
      'class="bns-switch" '+
      'ng-class="{maybe: ctrl.app.is_partially_open && !ctrl.userRole}" '+
      'aria-label="Application status"><span ng-transclude></span></md-switch>',
    controller: 'BNSAppToggleController',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSAppToggleController ($scope, toast, appsManager) {

  var ctrl = this;
  ctrl.busy = false;
  ctrl.toggle = toggle;

  init();

  function init () {
    // delay init after fetching app by name
    if (ctrl.appName && !ctrl.app) {
      return appsManager.get(ctrl.appName, ctrl.group ? ctrl.group.id : ctrl.groupId, ctrl.type)
        .then(function success (app) {
          ctrl.app = app;

          return init();
        })
      ;
    }
    $scope.$watch('ctrl.app', function () {
      if (ctrl.app) {
        if (ctrl.userRole) {
          if (ctrl.userRole === 'family') {
            ctrl.status = ctrl.app.is_open_family;
          } else if (ctrl.userRole === 'teacher') {
            ctrl.status = ctrl.app.is_open_teacher;
          }
        } else {
          ctrl.status = ctrl.app.is_open;
        }
      }
    });
  }

  function toggle () {
    var currentStatus = ctrl.app.is_open;
    var groupId = ctrl.group ? ctrl.group.id : ctrl.groupId;
    var userRole = ctrl.userRole;

    ctrl.busy = true;
    appsManager.toggle(ctrl.app, groupId, ctrl.type, userRole)
      .then(success)
      .catch(error)
      .finally(end)
    ;

    function success () {
      if (ctrl.userRole) {
        if (ctrl.userRole === 'family') {
          ctrl.app.is_open_family = !ctrl.app.is_open_family;
        } else if (ctrl.userRole === 'teacher') {
          ctrl.app.is_open_teacher = !ctrl.app.is_open_teacher;
        }
        if (ctrl.app.is_open_family === ctrl.app.is_open_teacher) {
          ctrl.app.is_partially_open = false;
          ctrl.app.is_open = ctrl.app.is_open_family;
        } else {
          ctrl.app.is_partially_open = true;
        }
      } else {
        ctrl.app.is_open = !ctrl.app.is_open;
        ctrl.app.is_partially_open = false;
      }

      if (true === ctrl.notify || 'success' === ctrl.notify) {
        toast.success('APPS.FLASH_'+(ctrl.type||'APP').toUpperCase()+'_'+(ctrl.app.is_open ? 'OPEN' : 'CLOSE')+'_SUCCESS');
      }
    }
    function error () {
      ctrl.status = currentStatus;
      if (true === ctrl.notify || 'error' === ctrl.notify) {
        toast.error('APPS.FLASH_'+(ctrl.type||'APP').toUpperCase()+'_'+(ctrl.app.is_open ? 'CLOSE' : 'OPEN')+'_ERROR');
      }
    }
    function end () {
      ctrl.busy = false;
    }
  }

}

})(angular);
