'use strict';

angular.module('bns.workshop.document', [
  'ng-sortable',
  'bns.userDirectory.editableList',
  // configs
  'bns.workshop.document.config.states',
  // controllers
  'bns.workshop.document.topbarController',
  'bns.workshop.document.mainController',
  'bns.workshop.document.sceneController',
  'bns.workshop.document.sidebarController',
  'bns.workshop.document.panelGeneralController',
  'bns.workshop.document.panelPagesController',
  'bns.workshop.document.panelLayoutController',
  'bns.workshop.document.panelKitController',
  'bns.workshop.document.panelKitEditController',
  // directives
  'bns.workshop.document.panelStateWatch',
  'bns.workshop.document.panelToggle',
  'bns.workshop.document.layoutImage',
  'bns.workshop.document.pageWrite',
  'bns.workshop.document.pageLayoutZoneWrite',
  'bns.workshop.document.widgetGroupWrite',
])

;
