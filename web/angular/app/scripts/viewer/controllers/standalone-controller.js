'use strict';

angular.module('bns.viewer')

  .controller('ViewerStandaloneCtrl', function (bnsViewer, $stateParams, $scope) {
    var ctrl = this;

    this.init = function () {
      this.viewer = bnsViewer();
      this.viewer.activate({
        resourceId: $stateParams.resourceId,
        noClose: true,
      });
    };

    this.init();

    $scope.$on('$destroy', function () {
      ctrl.viewer.deactivate();
    });
  });
