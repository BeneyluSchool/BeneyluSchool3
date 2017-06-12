'use strict';

angular.module('bns.viewer.workshop.document.widget', [
  'bns.viewer.workshop.document.widgetRead',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.widget.bnsWorkshopDocumentWidget
   * @kind function
   *
   * @description
   * Generic directive for workshop widgets. Handles common behavior and
   * dispatches to -read or -write sub-directives.
   *
   * ** Attributes **
   * - `viewMode` : String `read` or `write`. Determines the sub-directive to
   *                use. Defaults to `read`.
   *
   * @example
   * <any bns-workshop-document-widget view-mode="write"></any>
   *
   * @returns {Object} The bnsWorkshopWidgetRead directive
   */
  .directive('bnsWorkshopDocumentWidget', function ($compile) {
    return {
      require: ['^bnsWorkshopDocumentPage'],
      compile: compile,
      terminal: true,
      priority: 1010,
    };

    function compile () {
      return function (scope, element, attrs, controllers) {
        var pageCtrl = controllers[0];

        // read mode is always enabled
        element.attr('bns-workshop-document-widget-read', attrs.bnsWorkshopDocumentWidget);

        // write mode on demand
        if ('write' === pageCtrl.viewMode) {
          element.attr('bns-workshop-document-widget-write', attrs.bnsWorkshopDocumentWidget);
        }

        element.removeAttr('bns-workshop-document-widget');
        $compile(element)(scope);
      };
    }
  })
;
