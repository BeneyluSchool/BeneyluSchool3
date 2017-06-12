'use strict';

angular.module('bns.workshop.audio.mainController', [])

.controller('WorkshopAudioMainController', function ($scope) {
  angular.element('#workshop').parent().addClass('audio');

  $scope.$on('$destroy', function () {
    angular.element('#workshop').parent().removeClass('audio');
  });
});
