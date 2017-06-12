'use strict';

angular.module('bns.mediaLibrary.viewer.panelStateWatch', [
  'bns.mediaLibrary.viewer.panelState',
])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.viewer.panelStateWatch.bnsMediaLibraryViewerPanelStateWatch
   * @kind function
   *
   * @description
   * A simple directive to apply css classes based on media library viewer panel
   * state.
   * If the panel is open, the `panel-expanded` class is added to the directive
   * element.
   *
   * @requires mediaLibraryViewerPanelState
   *
   * @returns {Object} the bnsMediaLibraryViewerPanelStateWatch directive
   */
  .directive('bnsMediaLibraryViewerPanelStateWatch', function (mediaLibraryViewerPanelState) {
    return {
      link: function (scope, element) {
        scope.$watch(function () {
          return mediaLibraryViewerPanelState;
        }, applyCssClass, true);

        function applyCssClass (state) {
          if (state.expanded) {
            element.addClass('panel-expanded');
          } else {
            element.removeClass('panel-expanded');
          }
        }
      }
    };

  })

;
