'use strict';

angular.module('bns.workshop.audio.recorder.service', [
  'bns.workshop.audio.audioContext',
  'bns.core.url',
])

  .factory('Recorder', function ($window, url) {
    var audioContext;

    var Recorder = function (config) {

      if (!Recorder.isRecordingSupported()) {
        throw 'Recording is not supported in this browser';
      }

      audioContext = new $window.AudioContext();

      config = config || {};
      config.recordOpus = (config.recordOpus === false) ? false : config.recordOpus || true;
      config.bitDepth = config.recordOpus ? 16 : config.bitDepth || 16;
      config.bufferLength = config.bufferLength || 4096;
      config.monitorGain = config.monitorGain || 0;
      config.numberOfChannels = config.numberOfChannels || 1;
      config.sampleRate = config.sampleRate || (config.recordOpus ? 48000 : this.audioContext.sampleRate);
      config.workerPath = config.workerPath || url.worker('workshop/audio/recorderWorker.js');
      config.streamOptions = config.streamOptions || {
        optional: [],
        mandatory: {
          googEchoCancellation: false,
          googAutoGainControl: false,
          googNoiseSuppression: false,
          googHighpassFilter: false
        }
      };

      this.config = config;
      this.state = 'inactive';
      this.eventTarget = $window.document.createDocumentFragment();
      this.audioContext = audioContext;
      this.createAudioNodes();
      this.initStream();
    };

    Recorder.isRecordingSupported = function () {
      return !!($window.AudioContext && $window.navigator.getUserMedia);
    };

    Recorder.prototype.addEventListener = function( type, listener, useCapture ){
      this.eventTarget.addEventListener( type, listener, useCapture );
    };

    Recorder.prototype.createAudioNodes = function () {
      var self = this;

      this.gainNode = this.audioContext.createGain();

      this.analyserNode = this.audioContext.createAnalyser();
      this.analyserNode.minDecibels = -90;
      this.analyserNode.maxDecibels = -10;
      this.analyserNode.smoothingTimeConstant = 0.85;

      this.scriptProcessorNode = this.audioContext.createScriptProcessor( this.config.bufferLength, this.config.numberOfChannels, this.config.numberOfChannels );
      this.scriptProcessorNode.onaudioprocess = function (e) { self.recordBuffers( e.inputBuffer ); };

      this.monitorNode = this.audioContext.createGain();
      this.setMonitorGain( this.config.monitorGain );

      if (this.config.sampleRate < this.audioContext.sampleRate) {
        this.createButterworthFilter();
      }
    };

    Recorder.prototype.createButterworthFilter = function () {
      this.filterNode = this.audioContext.createBiquadFilter();
      this.filterNode2 = this.audioContext.createBiquadFilter();
      this.filterNode3 = this.audioContext.createBiquadFilter();
      this.filterNode.type = this.filterNode2.type = this.filterNode3.type = 'lowpass';

      var nyquistFreq = this.config.sampleRate / 2;
      this.filterNode.frequency.value = this.filterNode2.frequency.value = this.filterNode3.frequency.value = nyquistFreq - ( nyquistFreq / 3.5355 );
      this.filterNode.Q.value = 0.51764;
      this.filterNode2.Q.value = 0.70711;
      this.filterNode3.Q.value = 1.93184;

      this.filterNode.connect(this.filterNode2);
      this.filterNode2.connect(this.filterNode3);
      this.filterNode3.connect(this.scriptProcessorNode);
    };

    Recorder.prototype.initStream = function () {
      var self = this;
      $window.navigator.getUserMedia({ audio : this.config.streamOptions }, success, error);

      function success (stream) {
        self.stream = stream;
        self.sourceNode = self.audioContext.createMediaStreamSource(stream);
        self.sourceNode.connect(self.gainNode);
        self.gainNode.connect(self.analyserNode);
        self.analyserNode.connect(self.filterNode || self.scriptProcessorNode);
        self.sourceNode.connect(self.monitorNode);
        self.eventTarget.dispatchEvent(new Event('streamReady'));
      }
      function error (e) {
        self.eventTarget.dispatchEvent(new ErrorEvent('streamError', { error: e }));
      }
    };

    Recorder.prototype.pause = function () {
      if (this.state === 'recording') {
        this.state = 'paused';
        this.eventTarget.dispatchEvent(new Event('pause'));
      }
    };

    Recorder.prototype.recordBuffers = function (inputBuffer) {
      if (this.state === 'recording') {

        var buffers = [];
        for (var i = 0; i < inputBuffer.numberOfChannels; i++) {
          buffers[i] = inputBuffer.getChannelData(i);
        }

        this.worker.postMessage({ command: 'recordBuffers', buffers: buffers });
        this.duration += inputBuffer.duration;
        this.eventTarget.dispatchEvent(new CustomEvent('duration', { 'detail': this.duration }));
        this.eventTarget.dispatchEvent(new CustomEvent('audioprocess', { 'detail': inputBuffer }));
      }
    };

    Recorder.prototype.removeEventListener = function (type, listener, useCapture) {
      this.eventTarget.removeEventListener(type, listener, useCapture);
    };

    Recorder.prototype.requestData = function (callback) {
      if (this.state !== 'recording') {
        this.worker.postMessage({ command: 'requestData' });
      }
    };

    Recorder.prototype.resume = function (callback) {
      if ( this.state === 'paused') {
        this.state = 'recording';
        this.eventTarget.dispatchEvent(new Event('resume'));
      }
    };

    Recorder.prototype.setMonitorGain = function (gain) {
      this.monitorNode.gain.value = gain;
    };

    Recorder.prototype.setGain = function (gain) {
      this.gainNode.gain.value = gain;
    };

    Recorder.prototype.start = function () {
      if (this.state === 'inactive' && this.sourceNode) {

        var that = this;
        this.worker = new Worker(this.config.workerPath);
        this.worker.addEventListener('message', function (e) {
          var blob = new Blob([e.data.data], { type: that.config.recordOpus ? 'audio/ogg' : 'audio/wav' });
          var wav;
          if (e.data.wav) {
            wav = new Blob([e.data.wav], { type: 'audio/wav' });
          } else {
            wav = blob;
          }
          that.eventTarget.dispatchEvent(new CustomEvent('dataAvailable', {
            'detail': {
              blob: blob,
              wav: wav,
            },
          }));
        });

        this.worker.postMessage({
          command: 'start',
          bitDepth: this.config.bitDepth,
          bufferLength: this.config.bufferLength,
          inputSampleRate: this.audioContext.sampleRate,
          numberOfChannels: this.config.numberOfChannels,
          outputSampleRate: this.config.sampleRate,
          recordOpus: this.config.recordOpus
        });

        this.state = 'recording';
        this.duration = 0;
        this.monitorNode.connect(this.audioContext.destination);
        this.scriptProcessorNode.connect(this.audioContext.destination);
        this.recordBuffers = function () { delete this.recordBuffers; }; // First buffer can contain old data
        this.eventTarget.dispatchEvent(new Event('start'));
        this.eventTarget.dispatchEvent(new CustomEvent('duration', { 'detail': this.duration }));
      }
    };

    Recorder.prototype.stop = function (reset) {
      if (this.state !== 'inactive') {
        var that = this;
        this.monitorNode.disconnect();
        this.scriptProcessorNode.disconnect();
        this.state = 'inactive';
        this.worker.addEventListener('message', function (e) {
          that.eventTarget.dispatchEvent(new Event('stop'));
        });
        if (!reset) {
          this.worker.postMessage({ command: 'requestData' });
        }
        this.worker.postMessage({ command: 'stop' });
      }
    };

    return Recorder;
  })

;
