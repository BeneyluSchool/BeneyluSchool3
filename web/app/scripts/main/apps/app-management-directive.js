(function (angular) {
'use strict'  ;

angular.module('bns.main.apps')

  .directive('bnsAppManagement', BNSAppManagementDirective)
  .controller('BNSAppManagementController', BNSAppManagementController)

;

/**
 * @ngdoc directive
 * @name bnsAppManagement
 * @module bns.main.apps
 *
 * @description
 * Offers management controls for an app
 *
 * ** Attributes **
 *  - `app` {Object} (required): The app to manage
 *  - `group` {Object} (required): The group where to manage the app. Required
 *                                 if no group id given
 *  - `groupId` {Integer} (required): ID of the group where to manage the app.
 *                                    Required if no group given.
 *  - `notify` {Boolean|String}: Whether to notify user of success (or failure)
 *                               of actions.
 *                               Can also be set to 'success' or 'error' to
 *                               notify only upon certain cases.
 *  - `with-favorites`: {Boolean} : Whether to add favorite/sort
 *                                  functionnalities. Defaults to false
 */
function BNSAppManagementDirective () {

  return {
    scope: {
      app: '=*',
      group: '=*',
      groupId: '=',
      notify: '=',
      type: '=',
      withFavorites: '=',
    },
    templateUrl: 'views/main/apps/bns-app-management.html',
    controller: 'BNSAppManagementController',
    controllerAs: 'management',
    bindToController: true,
  };

}

function BNSAppManagementController ($scope, $element, dialog, toast, appsManager) {

  var management = this;
  management.toggleFavorite = toggleFavorite;
  management.requestUninstall = requestUninstall;
  management.cancelUninstall = cancelUninstall;
  management.uninstall = uninstall;
  management.hasUninstall = false;
  management.busy = false;

  init();

  function init () {
    management.groupId = management.group ? management.group.id : management.groupId;
  }

  function toggleFavorite () {
    management.busy = true;

    return appsManager.toggleFavorite(management.app, management.groupId)
      .finally(end)
    ;
    function end () {
      management.busy = false;
    }
  }

  function requestUninstall () {
    management.hasUninstall = true;
  }

  function cancelUninstall () {
    management.hasUninstall = false;
  }

  function uninstall () {
    management.busy = true;

    return appsManager.uninstall(management.app, management.groupId, management.type)
      .then(success)
      .catch(error)
      .finally(end)
    ;

    function success () {
      $element.addClass('animate-reveal');
      $scope.$emit('bns.app.uninstall', management.app, management.groupId);
      if (true === management.notify || 'success' === management.notify) {
        toast.success('APPS.FLASH_APP_UNINSTALL_SUCCESS');
      }
    }
    function error () {
      if (true === management.notify || 'error' === management.notify) {
        toast.error('APPS.FLASH_APP_UNINSTALL_ERROR');
      }
    }
    function end () {
      management.busy = false;
    }
  }

}

})(angular);
