'use strict';

angular.module('bns.viewer.workshop.document.widgetGroup', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.widgetGroup.bnsWorkshopDocumentWidgetGroup
   * @kind function
   *
   * @description
   * Meta-directive for document widget groups. Adds the actual 'view'
   * directive, responsible for handling visual appearance of a widget group.
   * Also adds the 'write' directive if required, for edition.
   *
   * @example
   * <any bns-workshop-document-widget-group="myWidgetGroup"></any>
   *
   * @returns {Object} The bnsWorkshopDocumentWidgetGroup directive
   */
  .directive('bnsWorkshopDocumentWidgetGroup', function ($compile) {
    return {
      replace: true,
      require: ['^bnsWorkshopDocumentPage'],
      compile: compile,
      terminal: true,
      priority: 1010, // before angular core (ng-repeat...)
    };

    function compile () {
      return function (scope, element, attrs, controllers) {
        var pageCtrl = controllers[0];

        // read mode is always enabled
        element.attr('bns-workshop-document-widget-group-read', attrs.bnsWorkshopDocumentWidgetGroup);

        // write mode on demand
        if ('write' === pageCtrl.viewMode) {
          element.attr('bns-workshop-document-widget-group-write', attrs.bnsWorkshopDocumentWidgetGroup);
        }

        element.removeAttr('bns-workshop-document-widget-group');
        $compile(element)(scope);
      };
    }
  })

  .directive('bnsWorkshopDocumentWidgetGroupRead', function ($compile, url) {
    return {
      replace: true,
      scope: {
        widgetGroup: '=bnsWorkshopDocumentWidgetGroupRead',
      },
      templateUrl: url.view('/viewer/workshop/document/directives/bns-workshop-document-widget-group.html'),
      controller: 'WorkshopDocumentWidgetGroupReadController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentWidgetGroupReadController', function () {
    var ctrl = this;
    ctrl.widgetGroupClass = 'workshop-widget-group-' + ctrl.widgetGroup.type;
  })
;
