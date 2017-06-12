'use strict';

angular.module('bns.viewer.workshop.document.pageLayout', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.pageLayout.bnsWorkshopDocumentPageLayout
   * @kind function
   *
   * @description
   * Responsible for handling visual appearance of the layout of a workshop
   * page.
   *
   * @example
   * <any bns-workshop-document-page-layout></any>
   *
   * @returns {Object} The bnsWorkshopDocumentPageLayout directive
   */
  .directive('bnsWorkshopDocumentPageLayout', function (url) {
    return {
      replace: true,
      scope: {
        last: '=',
        layout: '=bnsWorkshopDocumentPageLayout',
      },
      templateUrl: url.view('/viewer/workshop/document/directives/bns-workshop-document-page-layout.html'),
      controller: 'WorkshopDocumentPageLayoutController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentPageLayoutController', function ($scope, _) {
    var ctrl = this;
    ctrl.layoutClassPrefix = 'workshop-page-layout-';
    ctrl.layoutClasses = [];
    ctrl.rows = [];

    init();

    function init () {
      // watch for changes in layout (code), and update classes accordingly
      $scope.$watch('ctrl.layout.code', function () {
        if (!ctrl.layout) {
          return;
        }

        ctrl.layoutClasses = [];
        _.each(ctrl.layout.code.split('-'), function (code) {
          ctrl.layoutClasses.push(ctrl.layoutClassPrefix + code);
        });
      });

      $scope.$watchCollection('ctrl.layout.zones', function (zones) {
        if (!ctrl.layout) {
          return;
        }

        // group zones by row: header, content, footer
        ctrl.rows = [];
        _.each(zones, function (zone) {
          var parts = zone.code.split('-');
          var section = parts[0];
          var row = _.find(ctrl.rows, {code: section});
          if (!row) {
            row = { code: section, zones: [] };
            ctrl.rows.push(row);
          }
          row.zones.push(zone);
        });
      });
    }
  });
