'use strict';

angular.module('bns.workshop.audio.state', [])

  /**
   * Holds application-wide states of the audio document
   *
   * @return {Object}
   */
  .factory('WorkshopAudioState', function () {
    var service = {
      /**
       * The audio document being created
       *
       * @type {Object}
       */
      newAudio: null,
    };

    return service;
  });
