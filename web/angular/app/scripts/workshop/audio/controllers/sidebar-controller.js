'use strict';

angular.module('bns.workshop.audio.sidebarController', [
  'ui.router',
])

.controller('WorkshopAudioSidebarController', function ($state) {
  var ctrl = this;
  ctrl.$state = $state;
});
