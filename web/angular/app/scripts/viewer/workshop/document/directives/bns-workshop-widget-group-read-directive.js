'use strict';

angular.module('bns.viewer.workshop.document.widgetGroup')

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.widgetGroup.bnsWorkshopWidgetGroupRead
   * @kind function
   *
   * @description
   * Responsible for handling visual appearance of a widget group.
   *
   * @example
   * <any bns-viewer-workshop-widget-group="myWidgetGroup"></any>
   *
   * @returns {Object} The bnsWorkshopWidgetGroupRead directive
   */
  .directive('bnsWorkshopWidgetGroupRead', function () {
    return {
      replace: true,
      scope: true,
      templateUrl: '/ent/angular/app/views/viewer/workshop/document/directives/bns-workshop-widget-group-read.html',
      link: function (scope, element, attrs, ctrl) {
        ctrl.init();
      },
      controller: 'ViewerWorkshopWidgetGroupCtrl',
    };
  })

  .controller('ViewerWorkshopWidgetGroupCtrl', function ($scope) {

    this.init = function () {
      $scope.widgetGroupClass = 'workshop-widget-group-' + $scope.widgetGroup.type;
      $scope.widgets = $scope.widgetGroup._embedded.widgets;
    };

  });
