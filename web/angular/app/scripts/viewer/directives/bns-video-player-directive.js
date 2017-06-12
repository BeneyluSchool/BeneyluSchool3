'use strict';

angular.module('bns.viewer.videoPlayer', ['bns.mediaLibrary.mediaElementConfig'])

  .directive('bnsVideoPlayer', function ($timeout, BNS_MEDIAELEMENT_CONFIG) {
    return {
      link: function (scope, element, attrs) {
        $timeout(function () {
          element.mediaelementplayer(angular.extend({}, BNS_MEDIAELEMENT_CONFIG, {
            defaultVideoWidth: '80',
            // if the <video height> is not specified, this is the default
            defaultVideoHeight: '80',
            // if set, overrides <video width>
            videoWidth: '40',
            // if set, overrides <video height>
            videoHeight: '40'
          }));


        }, 0);
      }
    };
  })
