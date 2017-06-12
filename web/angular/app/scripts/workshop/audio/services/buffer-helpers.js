'use strict';

angular.module('bns.workshop.audio.audioBufferHelpers', [])

  /**
   * @ngdoc service
   * @name bns.workshop.audio.audioBufferHelpers
   * @kind function
   *
   * @description Utility functions for AudioBuffers.
   *
   * @returns {Object} The audioBufferHelpers service.
   */
  .factory('audioBufferHelpers', function ($window) {
    var audioContext;
    var srvc = {
      append: append,
      tail: tail,
    };

    return srvc;

    /**
     * Appends the two given AudioBuffers
     *
     * @param {AudioBuffer} buffer1
     * @param {AudioBuffer} buffer2
     * @returns {AudioBuffer} A new AudioBuffer
     */
    function append (buffer1, buffer2) {
      var numberOfChannels = Math.min( buffer1.numberOfChannels, buffer2.numberOfChannels );
      var tmp = getAudioContext().createBuffer(numberOfChannels, (buffer1.length + buffer2.length), buffer1.sampleRate);
      for (var i = 0; i < numberOfChannels; i++) {
        var channel = tmp.getChannelData(i);
        channel.set(buffer1.getChannelData(i), 0);
        channel.set(buffer2.getChannelData(i), buffer1.length);
      }

      return tmp;
    }

    /**
     * Gets the last seconds of the given AudioBuffer.
     *
     * @param {AudioBuffer} buffer
     * @param {Integer} seconds
     * @returns {AudioBuffer} A new AudioBuffer
     */
    function tail (buffer, seconds) {
      var frames = buffer.sampleRate * seconds;

      if (buffer.length <= frames) {
        return buffer;
      }

      var newBuffer = getAudioContext().createBuffer(buffer.numberOfChannels, frames, buffer.sampleRate);
      var tmp = new Float32Array(frames);
      var startFrame = buffer.length - frames;
      for (var i = 0; i < buffer.numberOfChannels; i++) {
        buffer.copyFromChannel(tmp, i, startFrame);
        newBuffer.copyToChannel(tmp, i, 0);
      }

      return newBuffer;
    }


    // -------------------------------------------------------------------------
    //  Implementation details
    // -------------------------------------------------------------------------

    function getAudioContext () {
      if (!$window.AudioContext) {
        throw 'No AudioContext found';
      }

      if (!audioContext) {
        audioContext = new $window.AudioContext();
      }

      return audioContext;
    }

  });
