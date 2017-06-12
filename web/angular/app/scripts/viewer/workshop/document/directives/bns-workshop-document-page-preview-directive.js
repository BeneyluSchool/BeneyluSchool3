'use strict';

angular.module('bns.viewer.workshop.document.pagePreview', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.pagePreview.bnsWorkshopDocumentPagePreview
   * @kind function
   *
   * @description
   * Displays a preview of the given page.
   *
   * @example
   * <any bns-workshop-document-page-preview="myDocumentPage"></any>
   *
   * @requires url
   *
   * @returns {Object}
   */
  .directive('bnsWorkshopDocumentPagePreview', function (url) {
    return {
      replace: true,
      templateUrl: url.view('viewer/workshop/document/directives/bns-workshop-document-page-preview.html'),
      scope: {
        page: '=bnsWorkshopDocumentPagePreview',
      },
      controller: 'WorkshopDocumentPagePreviewController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentPagePreviewController', function ($scope, url) {
    var ctrl = this;

    init();

    function init () {
      $scope.$watch('ctrl.page.layout_code', function (layoutCode) {
        if (!layoutCode) {
          layoutCode = 'empty';
        }
        ctrl.path = url.image('workshop/document/layouts/' + layoutCode + '.png');
      });
    }
  })

;
