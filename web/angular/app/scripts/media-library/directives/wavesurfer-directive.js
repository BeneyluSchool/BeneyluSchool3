'use strict';

angular.module('bns.mediaLibrary.wavesurfer', [])

  .factory('WaveSurfer', function ($window) {
    return $window.WaveSurfer;
  })

  .directive('wavesurfer', function (WaveSurfer) {
    return {
      scope: true,
      link: link,
      controller: 'WavesurferController',
      controllerAs: 'ctrl',
    };

    function link (scope, element, attrs) {
      element.css('display', 'block');
      var options = angular.extend({ container: element[0] }, attrs);
      var wavesurfer = WaveSurfer.create(options);

      if (attrs.url) {
        wavesurfer.load(attrs.url, attrs.data || null);
      }

      scope.$emit('wavesurferInit', wavesurfer);
    }
  })

  .controller('WavesurferController', function ($scope, $interval) {
    var ctrl = this;
    var activeUrl;

    $scope.$on('wavesurferInit', function (e, wavesurfer) {
      ctrl.wavesurfer = wavesurfer;

      wavesurfer.on('play', function () {
        $scope.$emit('wavesurfer.play');
      });

      wavesurfer.on('pause', function () {
        $scope.$emit('wavesurfer.pause');
      });

      wavesurfer.on('finish', function () {
        wavesurfer.pause(); // weird IE keeps playing from start...
        wavesurfer.seekTo(0);
        $scope.$emit('wavesurfer.finish');
        $scope.$apply();
      });

      wavesurfer.on('error', function (msg) {
        console.error('[wavesurfer]', msg);
        $scope.$emit('wavesurfer.error', msg);
      });

      wavesurfer.once('ready', function () {
        $scope.$emit('wavesurfer.ready');
      });

      augment(wavesurfer);
    });

    $scope.$on('wavesurferLoad', function (e, url) {
      load(url);
    });

    $scope.$on('wavesurferStream', function (e, buffer) {
      ctrl.wavesurfer.empty();
      ctrl.wavesurfer.loadDecodedBuffer(buffer);
    });

    $scope.$on('$destroy', function () {
      ctrl.wavesurfer.destroy();
    });

    function load (url) {
        if (!ctrl.wavesurfer) {
            return;
        }

        activeUrl = url;

        if ('object' === typeof activeUrl) {
          ctrl.wavesurfer.loadBlob(activeUrl);
        } else {
          ctrl.wavesurfer.load(activeUrl);
        }
    }

    /**
     * Augments the wavesurfer with additional capabilities
     *
     * @param  {WaveSurfer} wavesurfer
     */
    function augment (wavesurfer) {
      // keep a reference to the timer
      wavesurfer.playTimer = null;

      /**
       * Watch the wavesurfer play state every [interval] ms, and fires
       * appropriate events every second.
       *
       * @param {Integer} interval
       */
      wavesurfer.watch = function (interval) {
        var previousTime = -1;

        if (!interval) {
          interval = 100;
        }

        wavesurfer.on('play', function () {
          previousTime = -1;
          wavesurfer.playTimer = $interval(notifyTime, interval, 0, false);
        });
        wavesurfer.on('pause', function () {
          previousTime = -1;
          $interval.cancel(wavesurfer.playTimer);
        });
        wavesurfer.on('finish', function () {
          previousTime = -1;
          $interval.cancel(wavesurfer.playTimer);
        });
        wavesurfer.on('seek', function () {
          previousTime = -1;
          if (wavesurfer.backend.isPaused()) {
            notifyTime();
          }
        });

        $scope.$on('$destroy', function () {
          $interval.cancel(wavesurfer.playTimer);
        });

        function notifyTime () {
          var current = Math.floor(ctrl.wavesurfer.getCurrentTime());
          if (current > previousTime) {
            previousTime = current;
            $scope.$emit('wavesurfer.time', current);
            if (!$scope.$root.$$phase) {
              $scope.$apply();
            }
          }
        }
      };
    }

    $scope.isPlaying = function (url) {
        return url === activeUrl;
    };
  })

;
