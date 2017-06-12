(function (angular) {
'use strict';

angular.module('bns.starterKit.management', [])

  .directive('bnsStarterKitManagement', BNSStarterKitManagementDirective)
  .controller('BNSStarterKitManagement', BNSStarterKitManagementController)

;

function BNSStarterKitManagementDirective () {

  return {
    scope: {
      app: '@',
    },
    templateUrl: 'views/starter-kit/directives/bns-starter-kit-management.html',
    controller: 'BNSStarterKitManagement',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSStarterKitManagementController (toast, starterKit, StarterKitState, StarterKitStore) {

  var ctrl = this;
  ctrl.busy = false;
  ctrl.range = range;
  ctrl.doLevel = doLevel;

  init();

  function init () {
    ctrl.busy = true;

    return StarterKitState.one(ctrl.app).get()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (state) {
      return StarterKitStore.getSteps(ctrl.app)
        .then(function (steps) {
          ctrl.state = state;
          ctrl.steps = steps;
          getLevels();
        })
      ;
    }
    function error (response) {
      toast.error('STARTER_KIT.FLASH_GET_LEVELS_ERROR');
      throw response;
    }
    function end () {
      ctrl.busy = false;
    }
  }

  function getLevels () {
    ctrl.levels = ctrl.steps[0][0].data.levels;
  }

  function range (n) {
    var r = [];
    for (var i = 0; i < n; i++) {
      r.push(i);
    }

    return r;
  }

  function doLevel (level) {
    try {
      var targetStep = ctrl.steps[level][0];
      starterKit.boot(ctrl.app)
        .then(function success () {
          starterKit.navigate(targetStep).then(function () {
            starterKit.enable();
          });
        })
      ;
    } catch (e) {
      toast.error('STARTER_KIT.FLASH_START_LEVEL_ERROR');
    }
  }

}

})(angular);
