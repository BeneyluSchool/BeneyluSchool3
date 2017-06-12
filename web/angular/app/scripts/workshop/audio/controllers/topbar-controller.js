'use strict';

angular.module('bns.workshop.audio.topbarController', [
  'bns.workshop.audio.state',
  'bns.workshop.audio.manager',
  'bns.workshop.audio.uploadProgressModal',
])

.controller('WorkshopAudioTopbarController', function (WorkshopAudioState, workshopAudioUploadProgressModal) {
  var ctrl = this;
  ctrl.state = WorkshopAudioState;
  ctrl.canSave = canSave;
  ctrl.save = save;

  function canSave () {
    return !ctrl.state.invalid && ctrl.state.newAudio && ctrl.state.newAudio.data;
  }

  function save () {
    if (!canSave()) {
      return false;
    }

    workshopAudioUploadProgressModal.audio = ctrl.state.newAudio;
    workshopAudioUploadProgressModal.activate();
  }
});
