'use strict';

angular.module('bns.workshop.widget.option')

  .directive('bnsWorkshopWidgetOptionSelect', function () {
    return {
      restrict: 'AE',
      scope: {
        current: '=',   // current value, to be highlighted in the UI
        option: '=',    // the theme options
        target: '@',    // the targeted model property
        allowEmpty: '=' // whether to allow empty selection
      },
      link: function (scope, element, attrs, WorkshopWidgetOptionSelectCtrl) {
        WorkshopWidgetOptionSelectCtrl.init();
      },
      templateUrl: '/ent/angular/app/views/workshop/widget/option/select.html',
      controller: 'WorkshopWidgetOptionSelectCtrl',
    };
  })

  .controller('WorkshopWidgetOptionSelectCtrl', function ($scope) {
    this.init = function () {
      $scope.choice = $scope.current ? $scope.current : $scope.option.default_value;

      // watch for local changes, and emit the corresponding event
      $scope.$watch('choice', function (newChoice) {
        if (undefined !== newChoice) {
          $scope.$emit('widget.option.changed', $scope.target, newChoice);
        }
      });
    };
  });
