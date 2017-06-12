'use strict';

angular.module('bns.workshop.audio.audioContext', [])

.run(function ($window) {
  $window.AudioContext =
    $window.AudioContext ||
    $window.webkitAudioContext ||
    $window.mozAudioContext;

  $window.navigator.getUserMedia =
    $window.navigator.getUserMedia ||
    $window.navigator.webkitGetUserMedia ||
    $window.navigator.mozGetUserMedia ||
    $window.navigator.msGetUserMedia;
});
