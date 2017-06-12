(function (angular) {
'use strict';

angular.module('bns.starterKit.progress', [])

  .directive('bnsStarterKitProgress', BNSStarterKitProgressDirective)
  .controller('BNSStarterKitProgress', BNSStarterKitProgressController)

;

function BNSStarterKitProgressDirective () {

  return {
    templateUrl: 'views/starter-kit/directives/bns-starter-kit-progress.html',
    scope: true,
    controller: 'BNSStarterKitProgress',
    controllerAs: 'ctrl',
  };

}

function BNSStarterKitProgressController (_, $scope, $element, starterKit) {

  var ctrl = this;
  ctrl.exit = exit;
  ctrl.navigate = navigate;
  ctrl.getSteps = getSteps;
  ctrl.getStepClass = getStepClass;

  init();

  function init () {
    $scope.$watch(function () {
      return starterKit.enabled && starterKit.app && starterKit.current && (starterKit.level > 0);
    }, function (isEnabled) {
      ctrl.enabled = isEnabled;
      if (isEnabled) {
        $element.addClass('enabled');
      } else {
        $element.removeClass('enabled');
      }
    });
  }

  function exit () {
    return starterKit.suspend();
  }

  function navigate (step) {
    if (step.step >= starterKit.current.step) {
      return;
    }

    return starterKit.navigate(step);
  }

  function getSteps () {
    return _.uniq(starterKit.getSteps(), true, function (step) {
      return starterKit.getStepSection(step);
    });
  }

  function getStepClass (step) {
    if (starterKit.getStepSection(step) === starterKit.getStepSection(starterKit.current)) {
      return'step-current md-accent'; // current
    } else if (step.step < starterKit.current.step) {
      return 'step-past md-primary'; // done
    } else {
      return 'step-future md-primary md-hue-3'; // regular
    }
  }

}

})(angular);
