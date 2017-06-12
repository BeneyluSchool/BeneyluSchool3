(function (angular) {
'use strict';

angular.module('bns.twoDegrees.challengeSolver', [])

  .directive('bnsTwoDegreesChallengeSolver', BNSTwoDegreesChallengeSolverDirective)

;

function BNSTwoDegreesChallengeSolverDirective () {

  return {
    templateUrl: 'views/two-degrees/directives/bns-two-degrees-challenge-solver.html',
    scope: true,
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    // expose attrs to scope
    scope.letters = attrs.letters;
    scope.items = scope.$eval(attrs.items);

    // build validation
    scope.regex = new RegExp('^['+scope.letters+']*$');
    scope.min = attrs.length || attrs.min || 0;
    scope.max = attrs.length || attrs.max || (scope.letters && scope.letters.length) || 0;

    scope.placeholder = attrs.placeholder || 'TWO_DEGREES.PLACEHOLDER_INPUT_PASSCODE';
  }

}

})(angular);
