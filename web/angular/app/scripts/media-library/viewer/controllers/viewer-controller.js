'use strict';

angular.module('bns.mediaLibrary.viewer')

  .controller('MediaLibraryViewerCtrl', function ($scope, $rootScope, $stateParams, message, MediaLibraryRestangular, mediaLibraryConfig, mediaLibraryFilesFilter, stringHelpers, CollectionMap) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.isSelectionMode = 'selection' === mediaLibraryConfig.mode;

      // build a local selection, keeping only files
      $scope.selection = new CollectionMap(mediaLibraryFilesFilter($scope.shared.selection.list));

      $scope.form = {
        properties: ['label', 'description'],   // list of exposed properties
        initial: {},                            // initial values
        edit: false,
        busy: false,
      };

      var mediaId = $stateParams.id;

      this.media = MediaLibraryRestangular.one('media', mediaId);

      // media already loaded, just set it
      if ($scope.shared.mediaCache.isset(mediaId)) {
        ctrl.setupMedia($scope.shared.mediaCache.get(mediaId));
        return;
      }

      // media not loaded, need to fetch it
      this.loadMedia();
    };

    ctrl.loadMedia = function () {
      var params = {};
      if (angular.isDefined(mediaLibraryConfig.objectType) && angular.isDefined(mediaLibraryConfig.objectId)) {
        params.objectType = mediaLibraryConfig.objectType;
        params.objectId = mediaLibraryConfig.objectId;
      }

      this.media.get(params)
        .then(ctrl.getMediaSuccess, ctrl.getMediaError)
      ;
    };

    ctrl.setupMedia = function (media) {
      $scope.media = media;
      ctrl.cacheFormData();
    };

    // copy editable properties, so we can rollback them
    ctrl.cacheFormData = function () {
      angular.forEach($scope.form.properties, function (prop) {
        $scope.form.initial[prop] = $scope.media[prop];
      });
    };

    ctrl.getMediaSuccess = function (media) {
      $scope.shared.mediaCache.add(media);  // add fresh media to cache
      ctrl.setupMedia(media);
    };

    ctrl.getMediaError = function (response) {
      message.error('VIEWER.GET_MEDIA_ERROR');
      console.error('getMedia', response);
    };

    ctrl.remove = function () {
      ctrl.media.remove()
        .then(ctrl.deleteMediaSuccess, ctrl.deleteMediaError)
      ;
    };

    ctrl.deleteMediaSuccess = function (response) {
      console.log(response);
      message.success('MEDIA_LIBRARY.MOVE_TRASH_DOCUMENT_SUCCESS');
      $scope.navigateBack();
    };

    ctrl.deleteMediaError = function (response) {
      console.error('deleteMedia', response);
      message.error('MEDIA_LIBRARY.MOVE_TRASH_DOCUMENT_ERROR');
    };

    ctrl.togglePrivate = function () {
      ctrl.media.one('toggle-private')
        .patch()
        .then(ctrl.togglePrivateSuccess, ctrl.togglePrivateError)
      ;
    };

    ctrl.togglePrivateSuccess = function (media) {
      if (media.is_private) {
        message.success('MEDIA_LIBRARY.PRIVATIZE_DOCUMENT_SUCCESS');
        $scope.media.is_private = true;
      } else {
        message.success('MEDIA_LIBRARY.PUBLICIZE_DOCUMENT_SUCCESS');
        $scope.media.is_private = false;
      }
    };

    ctrl.togglePrivateError = function (response) {
      console.error('togglePrivate', response);
      if ($scope.media.is_private) {
        message.error('MEDIA_LIBRARY.PUBLICIZE_DOCUMENT_ERROR');
      } else {
        message.error('MEDIA_LIBRARY.PRIVATIZE_DOCUMENT_ERROR');
      }
    };

    ctrl.toggleFavorite = function () {
      ctrl.media.one('toggle-favorite')
        .patch()
        .then(ctrl.toggleFavoriteSuccess, ctrl.toggleFavoriteError)
      ;
    };

    ctrl.toggleFavoriteSuccess = function (media) {
      if (media.is_favorite) {
        message.success('MEDIA_LIBRARY.FAVORITE_DOCUMENT_SUCCESS');
        $scope.media.is_favorite = true;
      } else {
        message.success('MEDIA_LIBRARY.UNFAVORITE_DOCUMENT_SUCCESS');
        $scope.media.is_favorite = false;
      }
    };

    ctrl.toggleFavoriteError = function (response) {
      console.error('toggleFavorite', response);
      if ($scope.media.is_favorite) {
        message.error('MEDIA_LIBRARY.UNFAVORITE_DOCUMENT_ERROR');
      } else {
        message.error('MEDIA_LIBRARY.FAVORITE_DOCUMENT_ERROR');
      }
    };

    ctrl.copyInDocuments = function () {
      ctrl.media.one('file-my-copy')
        .post()
        .then(ctrl.copyInDocumentsSuccess, ctrl.copyInDocumentsError)
      ;
    };

    ctrl.copyInDocumentsSuccess = function (response) {
      message.success('MEDIA_LIBRARY.COPY_IN_DOCUMENTS_SUCCESS');
    };

    ctrl.copyInDocumentsError = function (response) {
      message.error('MEDIA_LIBRARY.COPY_IN_DOCUMENTS_ERROR');
      console.error('copyInDocuments', response);
    };

    ctrl.editSuccess = function () {
      message.success('MEDIA_LIBRARY.EDIT_DOCUMENT_SUCCESS');
      $scope.form.edit = false;

      // update cache with new values
      ctrl.cacheFormData();

      ctrl.editFinally();
    };

    ctrl.editError = function (response) {
      console.error('patchMedia', response);
      message.error('MEDIA_LIBRARY.EDIT_DOCUMENT_ERROR');

      ctrl.editFinally();
    };

    ctrl.editFinally = function () {
      $scope.form.busy = false;
    };

    ctrl.init();

    // Checks if selection must be displayed, i.e. current media is in it. This
    // implies that the collection is not empty.
    $scope.hasSelection = function () {
      return $scope.selection.has($scope.media);
    };

    $scope.remove = function () {
      ctrl.remove();
    };

    $scope.togglePrivate = function () {
      ctrl.togglePrivate();
    };

    $scope.toggleFavorite = function () {
      ctrl.toggleFavorite();
    };

    $scope.copyInDocuments = function () {
      ctrl.copyInDocuments();
    };

    $scope.joinMedia = function () {
      if ($scope.isSelectable($scope.media)) {
        $rootScope.$emit('mediaLibrary.selection.joinRequest', [$scope.media]);
      }
    };

    $scope.save = function () {
      $scope.form.busy = true;
      var formData = {};
      angular.forEach($scope.form.properties, function (prop) {
        formData[prop] = $scope.media[prop];
      });
      ctrl.media.patch(formData)
        .then(ctrl.editSuccess, ctrl.editError)
      ;
    };

    $scope.rollback = function () {
      for (var prop in $scope.form.initial) {
        $scope.media[prop] = $scope.form.initial[prop];
      }
      $scope.form.edit = false;
    };

    $scope.edit = function () {
      $scope.form.edit = true;
    };
  });
