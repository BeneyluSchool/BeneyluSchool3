'use strict';

angular.module('bns.workshop', [
  // configs
  'bns.workshop.config.states',
  // controllers
  'bns.workshop.indexController',
  // submodules
  'bns.workshop.content',   // generic content
  'bns.workshop.document',
  'bns.workshop.widget',    // TODO: move into document
  'bns.workshop.audio',

  // 'ui.router',
  // 'bns.core',
  // 'bns.resource',
  // 'bns.workshop.page',
  // 'bns.workshop.theme',
]);
