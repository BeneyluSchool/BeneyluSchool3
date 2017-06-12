'use strict';

angular.module('bns.workshop.document.panelStateWatch', [
  'bns.workshop.document.panelState',
])

  /**
   * @ngdoc directive
   * @name bns.workshop.document.panelStateWatch.bnsWorkshopDocumentPanelStateWatch
   * @kind function
   *
   * @description
   * A simple directive to apply css classes based on workshop document panel
   * state.
   * If the panel is open, the `panel-expanded` class is added to the directive
   * element.
   * If the panel is a large one, the `panel-large` class is added to the
   * directive element.
   *
   * @requires $timeout
   * @requires $window
   * @requires workshopDocumentPanelState
   *
   * @returns {Object} the bnsWorkshopDocumentPanelStateWatch directive
   */
  .directive('bnsWorkshopDocumentPanelStateWatch', function ($timeout, $window, workshopDocumentPanelState) {
    return {
      link: function (scope, element, attrs) {
        var duration = scope.$eval(attrs.duration);

        scope.$watch(function () {
          return workshopDocumentPanelState;
        }, applyCssClass, true);

        function applyCssClass (state) {
          if (state.expanded) {
            element.addClass('panel-expanded');
          } else {
            element.removeClass('panel-expanded');
          }

          if (state.large) {
            element.addClass('panel-large');
          } else {
            element.removeClass('panel-large');
          }

          if (duration) {
            $timeout(function () {
              angular.element($window).trigger('resize');
            }, duration);
          }
        }
      }
    };

  })

;
