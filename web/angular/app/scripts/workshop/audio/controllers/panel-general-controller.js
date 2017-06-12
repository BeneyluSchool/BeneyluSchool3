'use strict';

angular.module('bns.workshop.audio.panelGeneralController', [
  'bns.workshop.audio.state',
])

.controller('WorkshopAudioPanelGeneralController', function ($scope, WorkshopAudioState) {
  var ctrl = this;

  ctrl.state = WorkshopAudioState;

  $scope.$watch('documentForm.$invalid', function (invalid) {
    ctrl.state.invalid = invalid;
  });
});
