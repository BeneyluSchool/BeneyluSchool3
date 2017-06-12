'use strict';

angular.module('bns.workshop.widget.option')

  .directive('bnsWorkshopWidgetOptionColor', function (url) {
    return {
      restrict: 'AE',
      scope: {
        widget: '=',  // the widget object
        current: '=', // current value, to be highlighted in the UI
        option: '=',  // list of options
        target: '@'   // the targeted model property
      },
      link: function (scope, element, attrs, ctrl) {
        ctrl.init();
      },
      templateUrl: url.view('workshop/widget/option/color.html'),
      controller: 'WorkshopWidgetOptionColorController',
    };
  })

  .controller('WorkshopWidgetOptionColorController', function ($scope) {
    this.init = function () {};

    /**
     * Selects the given value. Emits the corresponding event
     *
     * @param {String} value
     */
    $scope.selectValue = function (value) {
      if ($scope.widget.settings && $scope.widget.settings[$scope.target] === value) {
        // reset value automatically
        $scope.$emit('widget.option.changed', $scope.widget, $scope.target);
      } else {
        $scope.$emit('widget.option.changed', $scope.widget, $scope.target, value);
      }
    };
  });
