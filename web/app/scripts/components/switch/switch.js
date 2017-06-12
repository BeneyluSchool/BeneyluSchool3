(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.switch
 */
angular.module('bns.components.switch', [])

  .directive('bnsSwitch', BNSSwitchDirective)
  .controller('BNSSwitch', BNSSwitchController)

;

/**
 * @ngdoc directive
 * @name bnsSwitch
 * @module bns.components.switch
 *
 * @description
 * Wrapper for a switch which value is handled by a manager. The manager must
 * implement an interface with promises. This allows for dialogs with a remote
 * source (ie API).
 *
 * ** Attributes **
 *  - `manager` {Object}: A simple JS object that must implement the following
 *    methods:
 *      - `getStatus()`: Must return a promise that gives an object with a
 *                       `status` property, corresponding to the current switch
 *                       value.
 *      - `toggle(value)`: Receives the wanted switch value as parameter. Should
 *                         return a promise that gives an object with a `status`
 *                         property, corresponding to the new switch value.
 */
function BNSSwitchDirective () {

  return {
    scope: {
      manager: '=',
    },
    transclude: true,
    template: '<md-switch '+
      'ng-disabled="!switch.manager || switch.busy" '+
      'ng-model="switch.status" '+
      'ng-change="switch.toggle()" '+
      'class="bns-switch layout-row layout-align-space-between-center md-secondary" '+
      '><span ng-transclude class="switch-label"></span></md-switch>',
    controller: 'BNSSwitch',
    controllerAs: 'switch',
    bindToController: true,
  };

}

function BNSSwitchController ($scope) {

  var ctrl = this;
  ctrl.toggle = toggle;

  init();

  function init() {
    $scope.$watch('switch.manager', function (manager) {
      if (!manager) {
        return;
      }

      if (!validateManager()) {
        return console.warn('Switch could not be set up');
      }

      lock();
      ctrl.manager.getStatus()
        .then(applyStatus)
        .finally(unlock)
      ;
    });
  }

  function toggle () {
    lock();
    ctrl.manager.toggle(ctrl.status)
      .then(applyStatus)
      .catch(function error (response) {
        ctrl.status = !ctrl.status;
        throw response;
      })
      .finally(unlock)
    ;
  }

  function lock () {
    ctrl.busy = true;
  }

  function unlock () {
    ctrl.busy = false;
  }

  function applyStatus (response) {
    ctrl.status = !!response.status;

    return response;
  }

  function validateManager () {
    if (!ctrl.manager) {
      return console.warn('No switch manager set');
    }

    var functions = ['getStatus', 'toggle'];
    for (var i in functions) {
      if (!angular.isFunction(ctrl.manager[functions[i]])) {
        return console.warn('The switch manager must implement function `'+functions[i]+'`');
      }
    }

    return true;
  }

}

})(angular);
