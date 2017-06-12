'use strict';

angular

  .module('bns.workshop.audio.microphoneAccessModal', [
    'btford.modal',
    'bns.core.url',
  ])

  .factory('workshopAudioMicrophoneAccessModal', function (btfModal, url) {
    return btfModal({
      controller: 'WorkshopAudioMicrophoneAccessController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/workshop/audio/modals/microphone-access-modal.html'),
    });
  })

  .controller('WorkshopAudioMicrophoneAccessController', function (workshopAudioMicrophoneAccessModal, $state) {
    var ctrl = this;
    ctrl.closeModal = closeModal;
    ctrl.cancel = cancel;
    ctrl.reload = reload;

    function closeModal () {
      workshopAudioMicrophoneAccessModal.deactivate();
    }

    function cancel () {
      workshopAudioMicrophoneAccessModal.deactivate();
      $state.go('app.workshop.index');
    }

    function reload () {
      workshopAudioMicrophoneAccessModal.deactivate();
      $state.go($state.current, {}, {reload: true});
    }
  })

;
