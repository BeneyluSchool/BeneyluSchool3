'use strict';

angular.module('bns.viewer.audioPlayer', [
  'bns.core.url',
])

  .directive('bnsViewerAudioPlayer', function (url) {
    return {
      templateUrl: url.view('viewer/directives/bns-viewer-audio-player.html'),
      scope: {
        media: '=bnsViewerAudioPlayer',
        noVisualization: '=?',
        mediaId: '=?mediaId',
        linkClass: '@',
        linkId: '=?linkId'
      },
      controller: 'ViewerAudioPlayerController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('ViewerAudioPlayerController', function ($timeout, $rootScope, $scope, $element, $interval, MediaLibraryRestangular) {
    var $progress = $element.find('#progress');
    var $loadBar = $progress.find('.loaded');
    var $playBar = $progress.find('.played');
    $timeout(function () {
      $progress = $element.find('#progress');
    });
    var ctrl = this;
    ctrl.volume = 3;  // [0..6]
    ctrl.ready = false;
    ctrl.busy = false;
    ctrl.fallback = false;
    ctrl.getPlayProgress = getPlayProgress;
    ctrl.seek = seekClick;
    init();

    function init () {
      $scope.$on('wavesurferInit', function (e, wavesurfer) {
        ctrl.wavesurfer = wavesurfer;
        if (!ctrl.media && ctrl.mediaId) {
          return MediaLibraryRestangular.one('media', ctrl.mediaId).get({
            objectId: ctrl.linkId,
            objectType: ctrl.linkClass
          }).then(function (item){
              ctrl.media = item;
              initPlayer();
            })
            .catch(function (error) {
              $scope.error = 'MEDIA_LIBRARY.MEDIA_NOT_FOUND';
              throw error;
            })
            ;
        } else if (ctrl.media) {
          initPlayer();
        }
      });

      $scope.$on('wavesurfer.ready', function () {
        ctrl.ready = true;
        ctrl.busy = false;
        $element.find('.viewer-audio-player').addClass('play');
        $scope.$digest();
      });

      $scope.$on('wavesurfer.error', function () {
        ctrl.busy = false;
        ctrl.fallback = true;
        $scope.$digest();
      });
    }

    function initPlayer () {
      var playInterval;

      ctrl.wavesurfer.backend.setVolume(0.5);
      ctrl.wavesurfer.watch();
      ctrl.busy = true;
      ctrl.wavesurfer.on('loading', showLoadProgress);
      $scope.$broadcast('wavesurferLoad', ctrl.media.download_url);
      $scope.$on('volume.change', function (e, value) {
        ctrl.wavesurfer.backend.setVolume(value / 6);
      });
      $scope.$on('wavesurfer.play', startPlayProgress);
      $scope.$on('wavesurfer.pause', stopPlayProgress);
      $scope.$on('wavesurfer.finish', stopPlayProgress);
      $scope.$on('$destroy', stopPlayProgress);

      function startPlayProgress () {
        playInterval = $interval(function () {
          showPlayProgress(getPlayProgress());
        }, 100);
      }

      function stopPlayProgress () {
        $interval.cancel(playInterval);
      }
    }

    function seekClick (event) {
      var total = $progress.width();
      var current = event.clientX - $progress.offset().left;

      ctrl.wavesurfer.seekTo(current/total);
    }

    function showLoadProgress (percent) {
      $loadBar.css('width', percent + '%');
    }

    function showPlayProgress (percent) {
      $playBar.css('width', percent + '%');
    }

    function getPlayProgress () {
      if (!ctrl.wavesurfer) {
        return 0;
      }

      return Math.round(ctrl.wavesurfer.getCurrentTime() * 10000 / ctrl.wavesurfer.getDuration()) / 100;
    }
  })

;
