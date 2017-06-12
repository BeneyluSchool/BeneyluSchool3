'use strict';

angular.module('bns.mediaLibrary.scene.contentLoader', [])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.scene.contentLoader.bnsSceneContentLoader
   * @kind function
   *
   * @description
   * Meta-directive that loads the correct scene content display directive.
   *
   * @requires $compile
   * @requires mediaLibrarySceneManager
   *
   * @return {Object} the bnsSceneContentLoader directive.
   */
  .directive('bnsSceneContentLoader', function ($compile, mediaLibrarySceneManager) {
    return {
      link: function (scope, element) {
        if (!scope.shared.context) {
          return;
        }

        var display = mediaLibrarySceneManager.getDisplayForContext(scope.shared.context);
        if (!display) {
          console.warn('Could not guess display for context', scope.shared.context);
          return;
        }

        // add the new directive responsible for actual display
        element.attr('bns-scene-content-'+display, '');

        // remove current directive to avoid inf. loop
        element.removeAttr('bns-scene-content-loader');
        $compile(element)(scope);
      }
    };
  })

;
