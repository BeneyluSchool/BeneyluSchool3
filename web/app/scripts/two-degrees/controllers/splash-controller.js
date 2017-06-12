(function (angular) {
'use strict';

angular.module('bns.twoDegrees.splashController', [
  'bns.components.dialog',
])

  .controller('TwoDegreesSplash', TwoDegreesSplashController)

;

function TwoDegreesSplashController (dialog, $mdBottomSheet) {

  var ctrl = this;
  ctrl.showPresentation = showPresentation;

  function showPresentation () {
    $mdBottomSheet.show({
      templateUrl: 'views/two-degrees/splash-bottom-sheet.html',
      controller: ['$scope', '$mdDialog', function dialogCtrl ($scope, $mdDialog) {
        $scope.$mdDialog = $mdDialog;
      }],
      parent: angular.element('.two-degrees-splash-container'),
    });
  }

}

})(angular);
