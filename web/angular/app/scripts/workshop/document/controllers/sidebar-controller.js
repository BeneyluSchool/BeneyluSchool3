'use strict';

angular.module('bns.workshop.document.sidebarController', [
  'ui.router',
  'bns.workshop.document.state',
])

.controller('WorkshopDocumentSidebarController', function ($state, WorkshopDocumentState) {
  var ctrl = this;
  ctrl.$state = $state;

  ctrl.state = WorkshopDocumentState;

  ctrl.document = WorkshopDocumentState.document;
});
