'use strict';

angular.module('bns.viewer.videoPlayer', ['bns.mediaLibrary.mediaElementConfig'])

  .directive('bnsVideoPlayer', function ($timeout, BNS_MEDIAELEMENT_CONFIG) {
    return {
      link: function (scope, element, attrs) {
        var player;
        $timeout(function () {
          player = new MediaElementPlayer(element[0], angular.extend({}, BNS_MEDIAELEMENT_CONFIG, {
            stretching: 'auto',
          }));
        }, 0);

        scope.$on('$destroy', function () {
          if (player) {
            if (!player.paused) {
              player.pause();
            }
            player.remove();
          }
        });
      }
    };
  })
