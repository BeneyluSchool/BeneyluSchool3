(function (angular) {
'use strict';

angular.module('bns.starterKit.dialogControllers', [])

  // generic dialogs
  .controller('StarterKitStartDialog', StarterKitDialogController)
  .controller('StarterKitIntroductionDialog', StarterKitDialogController)
  .controller('StarterKitAchievementDialog', StarterKitDialogController)
  .controller('StarterKitPointerDialog', StarterKitDialogController)

  // specialized dialogs
  .controller('StarterKitExplanationDialog', StarterKitExplanationDialogController)
  .controller('StarterKitStepperDialog', StarterKitStepperDialogController)
  .controller('StarterKitConclusionDialog', StarterKitConclusionDialogController)

;

function StarterKitDialogController (dialog, starterKit) {

  var ctrl = this;
  ctrl.starterKit = starterKit;
  ctrl.step = starterKit.getStep();
  ctrl.prev = prev;
  ctrl.next = next;
  ctrl.accept = accept;
  ctrl.decline = decline;
  ctrl.range = range;

  function accept () {
    return starterKit.next();
  }

  function decline (saveNextStep) {
    return starterKit.suspend(saveNextStep);
  }

  function prev () {
    return starterKit.prev();
  }

  function next () {
    return starterKit.next();
  }

  function range (n) {
    var r = [];
    for (var i = 0; i < n; i++) {
      r.push(i);
    }

    return r;
  }

}

function StarterKitExplanationDialogController ($scope, dialog, starterKit, target) {

  // extend base dialog
  StarterKitDialogController.call(this, dialog, starterKit);

  var ctrl = this;
  ctrl.targetTemplate = target ? target[0].outerHTML : null;

  init();

  function init () {
    if (!target) {
      return;
    }
    starterKit.activate(target);
  }

}

function StarterKitStepperDialogController ($rootScope, $scope, $window, dialog, starterKit) {

  // extend base dialog
  StarterKitDialogController.call(this, dialog, starterKit);

  var skdialog = this;
  var CONTENT_PADDING = 24;
  var TOOLBAR_HEIGHT = 64;

  init();

  function init () {
    if (skdialog.require) {
      $scope.$watch(function () {
        // 1 - evaluate the string passed to this controller to get the actual
        // scope expression
        // 2 - evaluate this expression to get the underlying value
        return $scope.$eval($scope.$eval('skdialog.require'));
      }, function (result) {
        skdialog.valid = !!result;
      });
    } else {
      skdialog.valid = true;
    }

    positionDialog();

    var unlisten = $rootScope.$on('starterkit.control.moved', positionDialog);
    angular.element($window).on('resize', positionDialog);
    $scope.$on('$destroy', function cleanup () {
      unlisten();
      angular.element($window).off('resize', positionDialog);
    });
  }

  function positionDialog () {
    if (!skdialog.source) {
      skdialog.position = false;
      return;
    }
    var rect = skdialog.source[0].getBoundingClientRect();
    if (!rect.width) {
      skdialog.position = false;
      return;
    }
    skdialog.position = {
      top: Math.max(rect.top - CONTENT_PADDING - TOOLBAR_HEIGHT, 0),
      left: Math.max(rect.left - CONTENT_PADDING, 0),
      width: Math.max(rect.width + 2 * CONTENT_PADDING, 100),
    };
  }

}

function StarterKitConclusionDialogController (dialog, starterKit) {

  // extend base dialog
  StarterKitDialogController.call(this, dialog, starterKit);

  var skdialog = this;
  skdialog.nextLevel = false;

  init();

  function init () {
    if (starterKit.steps[starterKit.level + 1]) {
      skdialog.nextLevel = starterKit.level + 1;
    }
  }

}

})(angular);
