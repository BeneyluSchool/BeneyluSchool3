'use strict';

angular

  .module('bns.workshop.audio.uploadProgressModal', [
    'btford.modal',
    'ui.router',
    'bns.core.url',
    'bns.workshop.audio.manager',
  ])

  .factory('workshopAudioUploadProgressModal', function (btfModal, url) {
    return btfModal({
      controller: 'WorkshopAudioUploadProgressController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/workshop/audio/modals/upload-progress-modal.html'),
    });
  })

  .controller('WorkshopAudioUploadProgressController', function (workshopAudioManager, workshopAudioUploadProgressModal) {
    var ctrl = this;
    ctrl.save = save;
    ctrl.closeModal = closeModal;
    ctrl.audio = workshopAudioUploadProgressModal.audio;

    save();

    function save () {
      ctrl.busy = true;
      ctrl.success = false;
      ctrl.error = false;
      return workshopAudioManager.save(ctrl.audio)
        .then(function success (audio) {
          ctrl.success = 'WORKSHOP.AUDIO.RECORD_UPLOAD_SUCCESS';
        })
        .catch(function error (message) {
          ctrl.error = message;
        })
        .finally(function end () {
          ctrl.busy = false;
        })
      ;
    }

    function closeModal () {
      workshopAudioUploadProgressModal.deactivate();
    }
  })

;
