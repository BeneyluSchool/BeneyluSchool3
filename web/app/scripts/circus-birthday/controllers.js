(function (angular) {
'use strict';

angular.module('bns.circusBirthday.controllers', [])

  .controller('CircusBirthdayDrawings', CircusBirthdayDrawingsController)
  .controller('CircusBirthdayDrawing', CircusBirthdayDrawingController)

;

function CircusBirthdayDrawingsController (hasAccessBack, drawings) {

  var ctrl = this;
  ctrl.busy = false;
  ctrl.drawings = drawings;
  ctrl.hasAccessBack = hasAccessBack;

}

function CircusBirthdayDrawingController ($sce, drawing, nextDrawing) {

  var ctrl = this;
  ctrl.busy = false;
  ctrl.drawing = drawing;
  ctrl.nextDrawing = nextDrawing;

  init();

  function init () {
    ctrl.drawing.trusted_video_url = $sce.trustAsResourceUrl(ctrl.drawing.video);
  }

}

})(angular);
