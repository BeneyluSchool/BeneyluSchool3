'use strict';

angular.module('bns.mediaLibrary.scene.contentGrouped', [])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.scene.contentGrouped.bnsSceneContentGrouped
   * @kind function
   *
   * @description
   * The Grouped scene grid and interactions.
   *
   * @return {Object} the bnsSceneContentGrouped directive.
   */
  .directive('bnsSceneContentGrouped', function () {
    return {
      templateUrl: '/ent/angular/app/views/media-library/directives/bns-scene-content-grouped-directive.html',
      link: function (scope, element, attrs, ctrl) {
        ctrl.init();
      },
      controller: 'MediaLibrarySceneContentGroupedCtrl',
    };
  })

  .controller('MediaLibrarySceneContentGroupedCtrl', function ($scope, mediaLibrarySceneManager) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.$watchCollection('shared.context.medias', function () {
        $scope.providers = mediaLibrarySceneManager.aggregateProviders($scope.shared.context.medias);
      });
    };

  })

;
