(function (angular) {
'use strict';

angular.module('bns.twoDegrees.thermometer', [])

  .directive('bnsTwoDegreesThermometer', BNSTwoDegreesThermometerDirective)
  .controller('BNSTwoDegreesThermometer', BNSTwoDegreesThermometerController)

;

function BNSTwoDegreesThermometerDirective () {

  return {
    templateUrl: 'views/two-degrees/directives/bns-two-degrees-thermometer.html',
    scope: {
      value: '=',
    },
    controller: 'BNSTwoDegreesThermometer',
    controllerAs: 'thermometer',
    bindToController: true,
  };

}

function BNSTwoDegreesThermometerController ($scope) {

  var MIN = 0;
  var MAX = 8;
  var STEPS = { // step n°: max %
    1: 40,
    2: 60,
    3: 80,
    4: 100
  };

  var thermometer = this;
  this.percent = 0;

  $scope.$watch('thermometer.value', updateValues);

  function updateValues (value) {
    thermometer.percent = Math.max(value - MIN, 0) / (MAX - MIN) * 100;

    for (var step in STEPS) {
      thermometer.step = parseInt(step, 10);
      if (STEPS[step] >= thermometer.percent) {
        break;
      }
    }
  }

}

})(angular);
