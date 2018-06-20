(function (angular) {
'use strict';


/**
 * @ngdoc module
 * @name bns.mediaLibrary.mediaPreview
 */
angular.module('bns.mediaLibrary.mediaPreview', [
  'bns.mediaLibrary.mediaManager',
])

  .directive('bnsMediaPreview', BNSMediaPreviewDirective)
  .controller('BNSMediaPreview', BNSMediaPreviewController)

;

/**
   * @ngdoc directive
   * @name bnMediaPreview
   * @module bns.mediaLibrary.mediaPreview
   *
   * @description
   * Displays the preview of a media element (document or folder)
   *
   * @returns {Object} The bnsMediaPreview directive
   */
function BNSMediaPreviewDirective () {

  return {
    scope: {
      item: '=?media',
      size: '@',
      defaultUrl: '=?',
      itemId: '=mediaId',
      linkId: '=?',
      linkClass: '@',
      withIcon: '@',
      viewMode: '=?viewMode'
    },

    templateUrl: 'views/media-library/directives/bns-media-preview.html',
    controller: 'BNSMediaPreview',
  };

}

function BNSMediaPreviewController (Routing, $scope, $attrs, parameters, mediaManager, Restangular) {

  // material design icons per media type
  var MD_ICONS = {
    image: 'photo',
    video: 'ondemand_video',
    audio: 'headset',
    link: 'link',
    document: 'description',
    file: 'description',
    embed: 'code',
    html_base: 'code',
    workshop_document: '',  // use custom image
    workshop_audio: '',     // use our custom icon
    workshop_questionnaire: '', // use our custom icon
  };

  var baseImgUrl = parameters.app_base_path + '/angular/app/images/media-library';

  $scope.$watch('item', updateMediaPreview);
  $scope.$watch('itemId', updateMediaFromItemId);

  if (!mediaManager.isFile($scope.item)) {
    $scope.$watch('item.is_empty', updateMediaPreview);
    $scope.$watch('item.is_locker', updateMediaPreview);
  }

  function updateMediaFromItemId() {
    if (!$scope.itemId) {
      return;
    }
    Restangular.all('media-library').one('media', $scope.itemId).get({
      objectId: $scope.linkId,
      objectType: $scope.linkClass,
    })
      .then(function (item) {
        $scope.item = item;
        $scope.label = item.label;
      })
      .catch(function (error) {
        $scope.error = 'MEDIA_LIBRARY.MEDIA_NOT_FOUND';
        throw error;
      })
    ;
  }

  function updateMediaPreview () {
    $scope.url = null;
    $scope.type = null;
    $scope.icon = null;

    if (!$scope.item) {
      return;
    }

    if (mediaManager.isFile($scope.item)) {
      if (mediaManager.isMediaWorkshopDocument($scope.item)) {
        $scope.url = baseImgUrl + '/workshop-document.png';
        if (!$scope.defaultUrl) {
          $scope.defaultUrl = $scope.url;
        }
      } else if (mediaManager.isMediaWorkshopAudio($scope.item)) {
        $scope.url = baseImgUrl + '/workshop-audio.png';
      } else if (mediaManager.isMediaWorkshopQuestionnaire($scope.item)) {
        $scope.url = baseImgUrl + '/questionnaire.png';
        if (!$scope.defaultUrl) {
          $scope.defaultUrl = $scope.url;
        }
      }

      // file preview is their image url
      if ($scope.item.image_thumb_url) {
        $scope.url = $scope.item.image_thumb_url;
      } else if ($scope.item.image_url) {
        $scope.url = $scope.item.image_url + '&size=small';
      }

      // if asked a specific size, use the url shortcut to it
      if ($scope.size) {
        $scope.url = Routing.generate('bns_app_medialibrary_front_imageurl', {
          id: $scope.item.id,
          size: $scope.size,
          objectId: $scope.linkId,
          objectType: $scope.linkClass,
        });
      }

      // no url found => fallback to icon
      if (!$scope.url || angular.isDefined($attrs.withIcon)) {
        var type = mediaManager.getMediaType($scope.item);
        $scope.icon = angular.isDefined(MD_ICONS[type]) ? MD_ICONS[type] : type;
      }
    } else if (mediaManager.isLockerFolder($scope.item)) {
      $scope.url = baseImgUrl + '/locker.png';
    } else {
      // no specific preview, fallback to generic images
      if (mediaManager.isFolder($scope.item)) {
        $scope.url = baseImgUrl + '/folder.png';

        if ($scope.item.is_empty === true ||
          ($scope.item.is_empty !== false &&
          !(($scope.item.children && $scope.item.children.length) ||
          ($scope.item.medias && $scope.item.medias.length)))) {
          $scope.url = baseImgUrl + '/folder-empty.png';
        }
      } else {
        if ($scope.defaultUrl) {
          $scope.url = $scope.defaultUrl;
        }
      }
    }
  }

}

})(angular);
