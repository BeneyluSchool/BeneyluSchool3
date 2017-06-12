'use strict';

angular.module('bns.viewer.workshop.document.directive', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.bnsWorkshopDocument
   * @kind function
   *
   * @description
   *
   * @example
   * <any bns-workshop-document="myDocument"></any>
   *
   * @returns {Object} The bnsWorkshopDocument directive
   */
  .directive('bnsWorkshopDocument', function (url) {
    return {
      scope: {
        document: '=bnsWorkshopDocument',
        hideNav: '=',
        print: '=',
      },
      templateUrl: url.view('viewer/workshop/document/directives/bns-workshop-document.html'),
      controller: 'WorkshopDocumentController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentController', function ($window, $timeout, $scope, workshopThemeStyler) {
    var ctrl = this;

    $timeout(function () {
      $window.status = 'done';
    }, 2000);

    $scope.$on('questionnaire.reset.click', function () {
      $scope.$broadcast('questionnaire.reset');
    });

    workshopThemeStyler.setTheme(ctrl.document._embedded.theme);
  });
