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
      item: '=media',
      defaultUrl: '=',
      itemId: '=mediaId',
      viewMode: '=?viewMode'
    },
    link: function (scope, element, attrs, ctrl) {
      ctrl.init();
    },
    templateUrl: 'views/media-library/directives/bns-media-preview.html',
    controller: 'BNSMediaPreview',
  };

}

function BNSMediaPreviewController ($scope, parameters, mediaManager, Restangular) {

  var baseImgUrl = parameters.app_base_path + '/angular/app/images/media-library';
  this.init = function () {
    $scope.url = null;
    $scope.type = null;

    if (mediaManager.isFile($scope.item)) {
      // file preview is their image url
      if ($scope.item.image_url) {
        $scope.url = $scope.item.image_url + '&size=small';
      } else if (mediaManager.isMediaWorkshopDocument($scope.item)) {
        $scope.url = baseImgUrl + '/workshop-document.png';
      } else if (mediaManager.isMediaWorkshopAudio($scope.item)) {
        $scope.url = baseImgUrl + '/workshop-audio.png';
      } else {
        $scope.type = mediaManager.getMediaType($scope.item);
      }
    } else if (mediaManager.isLockerFolder($scope.item)) {
      $scope.url = baseImgUrl + '/locker.png';
    } else {
      // no specific preview, fallback to generic images
      if (mediaManager.isFolder($scope.item)) {
        $scope.url = baseImgUrl + '/folder.png';

        if (!(($scope.item.children && $scope.item.children.length) ||
          ($scope.item.medias && $scope.item.medias.length))) {
          $scope.url = baseImgUrl + '/folder-empty.png';
        }
      } else {
        if ($scope.defaultUrl) {
          $scope.url = $scope.defaultUrl;
        }
      }
    }

    if (!$scope.item && $scope.itemId) {
      return Restangular.all('media-library').one('media', $scope.itemId).get()
        .then(function (item){
          $scope.url = item.image_url + '&size=small';
          $scope.label = item.label;
        })
        .catch(function (error){
          $scope.error = 'MEDIA_LIBRARY.MEDIA_NOT_FOUND';
          throw error;
        })
        ;
    }
  };

}

})(angular);
