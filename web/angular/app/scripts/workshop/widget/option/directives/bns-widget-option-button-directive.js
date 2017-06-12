'use strict';

angular.module('bns.workshop.widget.option')

  .directive('bnsWorkshopWidgetOptionButton', function () {
    return {
      restrict: 'AE',
      scope: {
        widget: '=',    // the widget object
        current: '=',   // current value, to be highlighted in the UI
        option: '=',    // the theme options
        target: '@',    // the targeted model property
      },
      link: function (scope, element, attrs, ctrl) {
        ctrl.init();
      },
      templateUrl: '/ent/angular/app/views/workshop/widget/option/button.html',
      controller: 'WorkshopWidgetOptionButtonController',
    };
  })

  .controller('WorkshopWidgetOptionButtonController', function ($scope) {
    this.init = function () {
      $scope.choice = $scope.current ? $scope.current : $scope.option.default_value;

      // watch for local changes, and emit the corresponding event
      $scope.$watch('choice', function (newChoice, oldChoice) {
        if (undefined !== newChoice && newChoice !== oldChoice) {
          $scope.$emit('widget.option.changed', $scope.widget, $scope.target, newChoice);
        }
      });
    };

    /**
     * Sets the current choice
     *
     * @param {String|Object} choice
     */
    $scope.setChoice = function (choice) {
      $scope.choice = choice;
    };
  });
