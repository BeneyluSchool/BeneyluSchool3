'use strict';

angular.module('bns.workshop.audio.sceneController', [
  'ui.router',
  'bns.core.url',
  'bns.workshop.audio.state',
  'bns.workshop.audio.manager',
  'bns.workshop.audio.recorder.service',
])

.controller('WorkshopAudioSceneController', function ($scope, $state, WorkshopAudioState, workshopAudioManager, Recorder) {
  var ctrl = this;
  ctrl.canRecord = Recorder.isRecordingSupported;

  init();

  function init () {
    // store recorded blob in local model
    $scope.$on('workshop.audio.created', function (event, data) {
      ctrl.newAudio.data = data;
    });

    $scope.$on('workshop.audio.deleted', function () {
      delete ctrl.newAudio.data;
    });

    if (!ctrl.canRecord()) {
      return;
    }

    ctrl.newAudio = WorkshopAudioState.newAudio = workshopAudioManager.create();

    $state.go('app.workshop.audio.create.index');
  }
});
