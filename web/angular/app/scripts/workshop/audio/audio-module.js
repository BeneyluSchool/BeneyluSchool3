'use strict';

angular.module('bns.workshop.audio', [
  // config
  'bns.workshop.audio.config.states',
  // controllers
  'bns.workshop.audio.topbarController',
  'bns.workshop.audio.sidebarController',
  'bns.workshop.audio.mainController',
  'bns.workshop.audio.sceneController',
  'bns.workshop.audio.panelGeneralController',
  // directives
  'bns.workshop.audio.recorder.directive',
  // services
  'bns.workshop.audio.recorder.service',
]);
