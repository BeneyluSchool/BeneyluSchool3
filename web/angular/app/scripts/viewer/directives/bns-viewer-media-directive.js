'use strict';

angular.module('bns.viewer.bnsViewerMedia', [
    'bns.core.url',
    'bns.viewer.gdocViewer',
    'bns.viewer.videoPlayer',
    'bns.viewer.embeddedVideo',
  ])

  .directive('bnsViewerMedia', function (url) {
    return {
      restrict: 'AE',
      replace: true,
      scope: {
        resource: '=media', // backward compatibility
        media: '=',
        lightweight: '=',
        showCaption: '=',
      },
      link: function (scope, element, attrs, ViewerMediaCtrl) {
        ViewerMediaCtrl.init(element);
      },
      controller: 'ViewerMediaCtrl',
      templateUrl: url.view('viewer/directives/bns-viewer-media.html'),
    };
  })

  .controller('ViewerMediaCtrl', function (url, gdocViewer, embeddedVideo, stringHelpers, $scope) {
    var ctrl = this;

    ctrl.init = function (element) {
      $scope.$watch('media', function (media) {
        if (!media) {
          $scope.caption = false;

          return;
        }

        var mediaType = getMediaType();
        $scope.mediaTemplate = url.view('viewer/media/' + getTemplateName() + '.html');
        $scope.mediaType = mediaType;
        element[0].className = element[0].className.replace(/\s(media-.*?)(?=\s)/g, ''); // clean old media classes
        element.addClass('media-' + mediaType);

        if (['LINK'].indexOf(media.type_unique_name) === -1) {
          $scope.caption = media.label;
        } else {
          $scope.caption = false;
        }
      });
    };

    $scope.getGdocUrl = function () {
      return gdocViewer.getUrl($scope.media.download_url);
    };

    /**
     * @deprecated
     */
    $scope.getEmbeddedVideoUrl = function () {
      var code = $scope.media.media_value.value;

      switch ($scope.media.media_value.type) {
        case 'youtube':
          return embeddedVideo.getYoutubeUrl(code);
        case 'dailymotion':
          return embeddedVideo.getDailymotionUrl(code);
        case 'vimeo':
          return embeddedVideo.getVimeoUrl(code);
      }
    };

    function getTemplateName () {
      var tplName = getMediaType();
      if ($scope.lightweight && ['file', 'audio', 'atelier-audio', 'document', 'link', 'atelier-document'].indexOf(tplName) > -1) {
        tplName += '-light';
      }

      return tplName;
    }

    function getMediaType () {
      return stringHelpers.snakeToDash($scope.media.type_unique_name).toLowerCase();
    }
  });
