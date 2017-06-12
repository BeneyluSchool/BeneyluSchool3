'use strict';

angular.module('bns.workshop.document.pageLayoutZoneWrite', [
  'bns.workshop.document.widgetGroupSortableConfiguration',
])

  /**
   * @ngdoc directive
   * @name bns.workshop.document.pageLayoutZoneWrite.bnsWorkshopDocumentPageLayoutZoneWrite
   * @kind function
   *
   * @description
   * Macro-directive for handling page layout zone edition, with drag & drop.
   *
   * @returns {Object} The bnsWorkshopDocumentPageLayoutZoneWrite directive
   *
   * @requires $compile
   */
  .directive('bnsWorkshopDocumentPageLayoutZoneWrite', function ($compile) {
    var compile = function () {
      return {
        pre: function () {},
        post: function (scope, element, attrs, controllers) {
          var pageCtrl = controllers[0];

          if ('write' === pageCtrl.viewMode) {
            // add the actual directive
            element.attr('bns-workshop-document-page-layout-zone-write-actual', '');

            // add drag & drop directive
            element.attr('ng-sortable', 'ctrl.sortableConf');
          }

          // remove any reference to this directive: avoid infinite loop!
          element.removeAttr('bns-workshop-document-page-layout-zone-write');
          element.removeAttr('data-bns-workshop-document-page-layout-zone-write');

          // compile the new stuff, and resume parsing of the subtree
          $compile(element)(scope);
        }
      };
    };

    return {
      restrict: 'A',
      terminal: true, // prevent further compilation (will be launched manually)
      priority: 1100, // execute before anything else
      compile: compile,
      require: ['^bnsWorkshopDocumentPage'],
    };
  })

  /**
   * @ngdoc directive
   * @name bns.workshop.page.bnsWorkshopDocumentPageLayoutZoneWriteActual
   * @kind function
   *
   * @description
   * Handles edition of page layout zones.
   *
   * @returns {Object} The bnsWorkshopDocumentPageLayoutZoneWriteActual directive
   */
  .directive('bnsWorkshopDocumentPageLayoutZoneWriteActual', function () {
    return {
      priority: -10, // our link() must be called before the one of ngSortable
      require: ['^bnsWorkshopDocumentPage', '^bnsWorkshopDocumentPageLayoutZone', 'bnsWorkshopDocumentPageLayoutZoneWriteActual'],
      link: function (scope, element, attrs, controllers) {
        var pageCtrl = controllers[0];
        var zoneCtrl = controllers[1];
        var writeCtrl = controllers[2];

        writeCtrl.bind(pageCtrl.page, zoneCtrl.zone, pageCtrl.document);
      },
      scope: true,
      controller: 'WorkshopDocumentPageLayoutZoneWriteController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentPageLayoutZoneWriteController', function (_, widgetGroupSortableConfiguration) {
    var ctrl = this;
    ctrl.bind = bind;

    /**
     * Called by the directive link, after controller instantiation and
     * dependency resolution
     */
    function bind (page, zone, document) {
      ctrl.page = page;
      ctrl.zone = zone;
      ctrl.document = document;
      ctrl.sortableConf = widgetGroupSortableConfiguration.get(ctrl.page, _.first(ctrl.zone.numbers));
    }
  });
