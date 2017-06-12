'use strict';

angular.module('bns.mediaLibrary.scene.contentTrash', [])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.scene.contentTrash.bnsSceneContentTrash
   * @kind function
   *
   * @description
   * The trash scene grid and interactions.
   *
   * @return {Object} the bnsSceneContentTrash directive.
   */
  .directive('bnsSceneContentTrash', function () {
    return {
      templateUrl: '/ent/angular/app/views/media-library/directives/bns-scene-content-trash-directive.html',
    };
  })

;
