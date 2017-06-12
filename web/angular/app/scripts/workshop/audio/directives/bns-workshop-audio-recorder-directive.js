'use strict';

angular.module('bns.workshop.audio.recorder.directive', [
  'bns.core.url',
  'bns.mediaLibrary.wavesurfer',
  'bns.workshop.audio.recorder.service',
  'bns.workshop.audio.volume',
  'bns.workshop.audio.visualization',
  'bns.workshop.audio.audioBufferHelpers',
  'bns.workshop.audio.microphoneAccessModal',
])

  .directive('bnsWorkshopAudioRecorder', function (url) {
    return {
      templateUrl: url.view('workshop/audio/directives/recorder.html'),
      controller: 'WorkshopAudioRecorderController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopAudioRecorderController', function ($interval, $scope, $element, $window, Recorder, audioBufferHelpers, workshopAudioMicrophoneAccessModal) {
    if (!Recorder.isRecordingSupported()) {
      return console.warn('Audio recording not supported');
    }

    var RECORDER_EVENT_NAMESPACE = 'workshop.audio.recorder';
    var PLAYER_EVENT_NAMESPACE = 'wavesurfer';
    var ctrl = this;
    ctrl.initStream = initStream;
    ctrl.hasAccess = false;
    ctrl.lastAction = null;
    ctrl.cssClasses = cssClasses;
    ctrl.controls = {
      record: {
        disabled: false,
        action: toggleRecord,
      },
      play: {
        disabled: true,
        action: togglePlay,
      },
      reset: {
        disabled: true,
        action: reset,
      },
    };

    var recorder;
    var gainNode;
    ctrl.config = {
      gain: 3,

      numberOfChannels: 1,
      bitDepth: 16,
      recordOpus: { bitRate: 64000, keepWav: true },
      sampleRate: 48000,
    };
    ctrl.volume = ctrl.config.gain;
    ctrl.record = {};

    activate();

    function activate () {
      initRecorder();

      $scope.$on('wavesurferInit', function (e, wavesurfer) {
        initPlayer(wavesurfer);
      });

      $scope.$on('volume.change', function (e, value) {
        e.stopPropagation();
        setVolume(value);
        if (!$scope.$root.$$phase) {
          $scope.$digest();
        }
      });

      $scope.$watch('ctrl.lastAction', function () {
        $scope.$broadcast('volume.set', getVolume());
      });

      if (!ctrl.hasAccess) {
        workshopAudioMicrophoneAccessModal.activate();
      }
    }

    function toggleRecord () {
      ctrl.lastAction = 'record';
      switch (ctrl.recorder.state) {
        case 'inactive':
          ctrl.recorder.start();
          break;
        case 'recording':
          ctrl.recorder.pause();
          ctrl.recorder.requestData();
          break;
        case 'paused':
          ctrl.recorder.resume();
          break;
      }
    }

    function togglePlay () {
      ctrl.lastAction = 'play';
      if (ctrl.wavesurfer) {
        ctrl.wavesurfer.playPause();
      }
    }

    function reset () {
      ctrl.lastAction = null;
      ctrl.recorder.stop(true);
      ctrl.record = {};
      ctrl.wavesurfer.empty();
      ctrl.controls.play.disabled = true;
      ctrl.controls.reset.disabled = true;
      $scope.$emit('workshop.audio.deleted');
    }

    function initRecorder () {
      var duration;

      ctrl.recorder = recorder = new Recorder(ctrl.config);

      // pipe recorder events to the angular world
      angular.forEach(['start', 'pause', 'resume', 'stop', 'streamError',
        'streamReady', 'dataAvailable', 'audioprocess'], function (eventName) {
          recorder.addEventListener(eventName, function (e) {
            $scope.$emit(RECORDER_EVENT_NAMESPACE+'.'+eventName, e);
          });
        }
      );

      // broadcast duration only every second (source event is dispatched ~ ten
      // times per second, it's too much for us)
      recorder.addEventListener('duration', function (e) {
        if (Math.floor(e.detail) > duration) {
          duration  = e.detail;
          $scope.$emit(RECORDER_EVENT_NAMESPACE+'.duration', e);
          $scope.$apply();
        }
      });

      // stream the last 5 seconds of audio
      var recordedBuffer;
      $scope.$on(RECORDER_EVENT_NAMESPACE+'.audioprocess', function (ngEvent, e) {
        if (!recordedBuffer) {
          recordedBuffer = e.detail;
        } else {
          recordedBuffer = audioBufferHelpers.tail(audioBufferHelpers.append(recordedBuffer, e.detail), 5);
        }
        $scope.$broadcast('wavesurferStream', recordedBuffer);
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.start', function () {
        ctrl.controls.play.disabled = true;
        ctrl.controls.reset.disabled = true;
        $scope.$emit('workshop.audio.deleted');

        // --
        duration = 0;
        ctrl.record.duration = 0;
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.pause', function () {
        ctrl.controls.play.disabled = false;
        ctrl.controls.reset.disabled = false;
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.resume', function () {
        ctrl.controls.play.disabled = true;
        ctrl.controls.reset.disabled = true;
        $scope.$emit('workshop.audio.deleted');
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.stop', function () {
        ctrl.controls.play.disabled = false;
        ctrl.controls.reset.disabled = false;
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.duration', function (ngEvent, e) {
        ctrl.record.duration = Math.floor(e.detail);
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.streamError', function (ngEvent, e) {
        ctrl.hasAccess = false;
        console.error('Recorder', e.error.name);
        workshopAudioMicrophoneAccessModal.activate();
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.streamReady', function () {
        $scope.$apply(function () {
          ctrl.hasAccess = true;
          workshopAudioMicrophoneAccessModal.deactivate();
        });

        gainNode = recorder.gainNode;

        $scope.$watch('ctrl.config.gain', function (gain) {
          gainNode.gain.value = gain;
        });
      });

      $scope.$on(RECORDER_EVENT_NAMESPACE+'.dataAvailable', function (ngEvent, e) {
        var blob = e.detail.blob;
        var wav = e.detail.wav;

        $scope.$apply(function () {
          ctrl.record.blob = blob;
          ctrl.record.wav = wav;
          $scope.$emit('workshop.audio.created', blob);
          $scope.$broadcast('wavesurferLoad', wav);
        });
      });
    }

    function initStream () {
      recorder.initStream();
    }

    function initPlayer (wavesurfer) {
      ctrl.wavesurfer = wavesurfer;
      ctrl.wavesurfer.backend.setVolume(0.5);

      $scope.$on(PLAYER_EVENT_NAMESPACE+'.play', function () {
        ctrl.controls.record.disabled = true;
        ctrl.controls.reset.disabled = true;
        setClass('playback-pause', false);
        setClass('playback-play');
      });

      $scope.$on(PLAYER_EVENT_NAMESPACE+'.pause', function () {
        ctrl.controls.record.disabled = false;
        ctrl.controls.reset.disabled = false;
        setClass('playback-play', false);
        setClass('playback-pause');
      });

      $scope.$on(PLAYER_EVENT_NAMESPACE+'.finish', function () {
        ctrl.controls.record.disabled = false;
        ctrl.controls.reset.disabled = false;
        setClass('playback-play', false);
        setClass('playback-pause', false);

      });

      ctrl.wavesurfer.watch();
    }

    function setClass (name, flag) {
      if (false === flag) {
        $element.find('.workshop-audio-recorder').removeClass(name);
      } else {
        $element.find('.workshop-audio-recorder').addClass(name);
      }
    }

    function cssClasses () {
      var classes = {};
      if (ctrl.lastAction) {
        classes[ctrl.lastAction] = true;
      }
      classes[ctrl.recorder.state] = true;

      return classes;
    }

    function setVolume (value) {
      if ('play' === ctrl.lastAction) {
        ctrl.wavesurfer.backend.setVolume(value / 6);
      } else {
        ctrl.config.gain = value;
      }
    }

    function getVolume () {
      if ('play' === ctrl.lastAction) {
        return Math.round(ctrl.wavesurfer.backend.getVolume() * 6);
      }

      return ctrl.config.gain;
    }

  })

;
