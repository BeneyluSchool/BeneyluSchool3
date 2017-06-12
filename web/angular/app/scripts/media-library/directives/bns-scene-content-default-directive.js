'use strict';

angular.module('bns.mediaLibrary.scene.contentDefault', [])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.scene.contentDefault.bnsSceneContentDefault
   * @kind function
   *
   * @description
   * The default scene grid and interactions.
   *
   * @return {Object} the bnsSceneContentDefault directive.
   */
  .directive('bnsSceneContentDefault', function () {
    return {
      templateUrl: '/ent/angular/app/views/media-library/directives/bns-scene-content-default-directive.html',
    };
  })

;
