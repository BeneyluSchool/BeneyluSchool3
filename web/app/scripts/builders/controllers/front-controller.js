(function (angular) {
'use strict';

angular.module('bns.builders.frontController', [
  'bns.builders.resources',
])

  .controller('BuildersFront', BuildersFrontController)

;

function BuildersFrontController ($scope, $state, bottomSheet, buildersResources, hasBack) {

  var ctrl = this;
  ctrl.showMenu = showMenu;
  $scope.hasBack = ctrl.hasBack = hasBack;     // state resolve
  $scope.resources = buildersResources;

  function showMenu () {
    return bottomSheet.show({
      templateUrl: 'views/builders/front/menu.html',
      scope: $scope,
      preserveScope: true,
    });
  }

}

})(angular);
