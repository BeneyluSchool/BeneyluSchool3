'use strict';

angular.module('bns.workshop.document.layoutImage', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.workshop.document.layoutImage.bnsWorkshopDocumentLayoutImage
   * @kind function
   *
   * @description
   * Displays an image of the given layout. Replaces the element.
   *
   * @example
   * <any bns-workshop-document-layout-image bns-layout="myLayout"></any>
   *
   * @returns {Object} The bnsWorkshopDocumentLayoutImage directive
   */
  .directive('bnsWorkshopDocumentLayoutImage', function () {
    return {
      template: '<img ng-src="{{ ctrl.path }}">',
      replace: true,
      scope: {
        layout: '=bnsLayout',
      },
      controller: 'WorkshopDocumentLayoutImageController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentLayoutImageController', function (url) {
      var ctrl = this;
      ctrl.path = url.image('workshop/document/layouts/' + ctrl.layout.code + '.png');
    })

;
