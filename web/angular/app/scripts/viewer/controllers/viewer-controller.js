'use strict';

angular.module('bns.viewer.controller', [
  'bns.core.message',
  'bns.core.stringHelpers',
  'bns.mediaLibrary.restangular',
])

  /**
   * @ngdoc controller
   * @name bns.viewer.controller.ViewerController
   * @kind function
   *
   * @description
   * The viewer controller. It is instanciated directly by the bnsViewer service
   * and its scope may be prepopulated with locals.
   *
   * Additionaly to normal DI, it receives some parameters:
   * - `viewer`: The object received from a call to bnsViewer, that has
   *             triggered this controller instanciation.
   *
   * @requires stringHelpers
   * @requires ResourceRestangular
   * @requires $scope
   * @requires $exceptionHandler
   */
  .controller('ViewerController', function ($scope, $exceptionHandler, viewer, message, stringHelpers, MediaLibraryRestangular) {
    var ctrl = this;

    // get locals from scope
    ctrl.media = $scope.media;
    ctrl.mediaId = $scope.mediaId;

    ctrl.busy = false;
    ctrl.viewer = viewer;
    ctrl.mediaWrapperClass = '';
    ctrl.deactivate = deactivate;

    init();

    function init () {
      if (!(ctrl.media || ctrl.mediaId)) {
        console.error('No media nor media id');

        return;
      }

      if (ctrl.media) {
        ctrl.busy = false;
        ctrl.mediaId = ctrl.media.id;

        // properly re-set the resource
        setupMedia(ctrl.media);
      } else {
        loadMedia(ctrl.mediaId);
      }
    }

    function loadMedia (id) {
      ctrl.busy = true;

      return MediaLibraryRestangular.one('media', id).get()
        .then(success)
        .catch(failure)
        .finally(end)
      ;

      function success (media) {
        setupMedia(media);
        return media;
      }
      function failure (response) {
        console.error('[GET media]', response);
        message.error('VIEWER.GET_MEDIA_ERROR');
        throw 'VIEWER.GET_MEDIA_ERROR';
      }
      function end () {
        ctrl.busy = false;
      }
    }

    function setupMedia (media) {
      ctrl.mediaWrapperClass = 'resource-' + stringHelpers.snakeToDash(media.type_unique_name).toLowerCase();
      ctrl.media = media;
    }

    function deactivate () {
      viewer.deactivate();
    }
  });
