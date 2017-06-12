'use strict';

angular.module('bns.mediaLibrary')

  .controller('MediaLibraryUploadCtrl', function ($scope, $rootScope, message, mediaLibraryUploader, ApiCodes, octetFilter) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.uploader = mediaLibraryUploader;

      // simple filter to prevent upload if no context
      $scope.uploader.filters.push({
        name: 'enforceContextFilter',
        fn: function enforceContextFilter () {
          return !!$scope.shared.context;
        },
      });

      $scope.uploader.filters.push({
        name: 'canManageFolderFilter',
        fn: function canManageFolderFilter () {
          return $scope.shared.canCreate;
        }
      });

      // set correct upload url just after adding file to queue, and remember
      // its context
      $scope.uploader.onAfterAddingFile = function (fileItem) {
        fileItem.url += '/' + $scope.shared.context.marker + '/file';
        fileItem.targetFolder = $scope.shared.context;
      };

      // add newly-uploaded media to local model
      $scope.uploader.onSuccessItem = function (fileItem, response, status, headers) {
        message.success('MEDIA_LIBRARY.UPLOAD_MEDIA_SUCCESS');

        $rootScope.$broadcast('mediaLibrary.media.created', headers.location, fileItem.targetFolder);
      };

      // reset upload state
      $scope.uploader.onErrorItem = function (fileItem, response) {
        fileItem.isUploaded = false;

        var code = response.error_code || response;

        var filename = fileItem.file.name;
        if (angular.isDefined(filename) && filename.length > 0 ) {
          filename = '"' + filename + '"';
        }

        switch (code) {
          case ApiCodes.ERROR_NO_ALLOWED_SPACE:
            message.error('MEDIA_LIBRARY.ERROR_USER_HAS_NO_FOLDER_ACCESS');
            break;
          case ApiCodes.ERROR_NOT_ENOUGH_SPACE_USER:
            message.error('MEDIA_LIBRARY.ERROR_NOT_ENOUGH_SPACE_USER');
            break;
          case ApiCodes.ERROR_NOT_ENOUGH_SPACE_GROUP:
            message.error('MEDIA_LIBRARY.ERROR_NOT_ENOUGH_SPACE_GROUP');
            break;
          case ApiCodes.ERROR_FILE_IS_TOO_LARGE:
            var maxSize = response.max_size || 50 * 1024 *1024;
            message.error('MEDIA_LIBRARY.ERROR_FILE_IS_TOO_LARGE', {file: filename, size: octetFilter(maxSize) });
            break;
          default:
            message.error('MEDIA_LIBRARY.UPLOAD_MEDIA_ERROR');
        }
      };
    };

    ctrl.init();
  });
