(function (angular) {
'use strict';

angular.module('bns.twoDegrees.panel', [])

  .directive('bnsTwoDegreesPanel', BNSTwoDegreesPanelDirective)

;

function BNSTwoDegreesPanelDirective () {

  return {
    restrict: 'E',
    template: '<md-content class="md-padding two-degrees-panel-content" ng-transclude></md-content>',
    transclude: true,
  };

}

})(angular);
