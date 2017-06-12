(function (angular) {
'use strict';

angular.module('bns.breakfastTour.navigation', [])

  .directive('bnsBreakfastTourNavigation', BNSBreakfastTourNavigationDirective)

;

function BNSBreakfastTourNavigationDirective () {

  return {
    restrict: 'E',
    templateUrl: 'views/breakfast-tour/bns-breakfast-tour-navigation.html',
  };

}

})(angular);
