(function (angular) {
'use strict';

angular.module('bns.starterKit.dialogControllers', [])

  // generic dialogs
  .controller('StarterKitDialog', StarterKitDialogController)
  .controller('StarterKitStartDialog', StarterKitDialogController)
  .controller('StarterKitIntroductionDialog', StarterKitDialogController)
  .controller('StarterKitAchievementDialog', StarterKitDialogController)
  .controller('StarterKitPointerDialog', StarterKitDialogController)
  .controller('StarterKitExplanationDialog', StarterKitDialogController)

  // specialized dialogs
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

  function decline (saveNextStep, linkUrl) {
    return starterKit.suspend(saveNextStep, linkUrl);
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

function StarterKitStepperDialogController (mdPanelRef, $rootScope, $scope, $window, dialog, starterKit) {

  // extend base dialog
  StarterKitDialogController.call(this, dialog, starterKit);

  var skdialog = this;
  var CONTENT_PADDING = 16;

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

    $scope.panelStyle = $scope.panelStyle || {};
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
      return;
    }
    var rect = skdialog.source[0].getBoundingClientRect();
    $scope.panelStyle.width = Math.max(rect.width + 2 * CONTENT_PADDING, 100);
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
