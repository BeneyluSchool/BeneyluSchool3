'use strict';

angular.module('bns.workshop.widget.option')

  /**
   * @ngdoc directive
   * @name bns.workshop.widget.option.bnsWorkshopWidgetOptionTemplate
   * @kind function
   *
   * @description
   * This is a simple directive to embed directly a widget option template.
   *
   * @example
   * <any bns-workshop-widget-option-template="myTemplateName"></any>
   *
   * @returns {Object} The bnsWorkshopWidgetOptionTemplate directive.
   */
  .directive('bnsWorkshopWidgetOptionTemplate', function () {
    return {
      restrict: 'AE',
      replace: true,
      templateUrl: function (element, attrs) {
        var templateName = attrs.bnsWorkshopWidgetOptionTemplate;

        return '/ent/angular/app/views/workshop/widget/option/' + templateName + '.html';
      }
    };
  });
