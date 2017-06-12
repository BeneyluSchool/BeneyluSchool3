'use strict';

angular.module('bns.workshop.document.panelToggle', [
  'bns.workshop.document.panelState',
])

  /**
   * @ngdoc directive
   * @name bns.workshop.document.panelToggle.bnsWorkshopDocumentPanelToggle
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
   * <any bns-workshop-document-panel-toggle></any>
   *
   * <!-- open/close based on an expression -->
   * <any bns-workshop-document-panel-toggle="myCoolExpression"></any>
   *
   * @requires workshopDocumentPanelState
   *
   * @returns {Object} the bnsWorkshopDocumentPanelToggle directive
   */
  .directive('bnsWorkshopDocumentPanelToggle', function (workshopDocumentPanelState) {
    return {
      link: function (scope, element, attrs) {

        element.click(togglePanelState);

        function togglePanelState () {

          scope.$apply(function () {
            // check if we have an explicit value
            var forcedValue = scope.$eval(attrs.bnsWorkshopDocumentPanelToggle);

            if (true === forcedValue || false === forcedValue) {
              // explicit value
              workshopDocumentPanelState.expanded = forcedValue;
            } else {
              // toggle
              workshopDocumentPanelState.expanded = !workshopDocumentPanelState.expanded;
            }
          });
        }
      }
    };
  })

;
