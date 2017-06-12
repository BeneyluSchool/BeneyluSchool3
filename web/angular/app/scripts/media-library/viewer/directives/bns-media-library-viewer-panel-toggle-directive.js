'use strict';

angular.module('bns.mediaLibrary.viewer.panelToggle', [
  'bns.mediaLibrary.viewer.panelState',
])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.viewer.panelToggle.bnsMediaLibraryViewerPanelToggle
   * @kind function
   *
   * @description
   * A simple directive to toggle the workshop document panel upon click.
   * Optionally, an expression can be given as directive argument. It will be
   * evaluated and if it strictly equals a boolean value, it will be used as the
   * new panel expanded state (ie. open or closed).
   *
   * @example
   * <!-- simple toggle -->
   * <any bns-media-library-viewer-panel-toggle></any>
   *
   * <!-- open/close based on an expression -->
   * <any bns-media-library-viewer-panel-toggle="myCoolExpression"></any>
   *
   * @requires $window
   * @requires mediaLibraryViewerPanelState
   *
   * @returns {Object} the bnsMediaLibraryViewerPanelToggle directive
   */
  .directive('bnsMediaLibraryViewerPanelToggle', function ($window, $timeout, mediaLibraryViewerPanelState) {
    return {
      link: function (scope, element, attrs) {

        element.click(togglePanelState);

        function togglePanelState () {

          scope.$apply(function () {
            // check if we have an explicit value
            var forcedValue = scope.$eval(attrs.bnsMediaLibraryViewerPanelToggle);

            if (true === forcedValue || false === forcedValue) {
              // explicit value
              mediaLibraryViewerPanelState.expanded = forcedValue;
            } else {
              // toggle
              mediaLibraryViewerPanelState.expanded = !mediaLibraryViewerPanelState.expanded;
            }

            var duration = scope.$eval(attrs.duration);
            if (duration) {
              $timeout(function () {
                angular.element($window).trigger('resize');
              }, duration);
            }
          });
        }
      }
    };
  })

;
