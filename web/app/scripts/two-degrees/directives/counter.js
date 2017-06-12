(function (angular) {
'use strict';

angular.module('bns.twoDegrees.counter', [])

  .directive('bnsTwoDegreesCounter', BNSTwoDegreesCounterDirective)
  .controller('BNSTwoDegreesCounter', BNSTwoDegreesCounterController)

;

function BNSTwoDegreesCounterDirective () {

  return {
    scope: {},
    controller: 'BNSTwoDegreesCounter',
    controllerAs: 'counter',
    bindToController: true,
  };

}

function BNSTwoDegreesCounterController ($element, TwoDegrees) {

  init();

  function init () {
    var counter = $element.FlipClock(1, {
      clockFace: 'Counter'
    });

    TwoDegrees.getTotal()
      .then(function success (value) {
        counter.setValue(value);
      })
    ;
  }

}

})(angular);
