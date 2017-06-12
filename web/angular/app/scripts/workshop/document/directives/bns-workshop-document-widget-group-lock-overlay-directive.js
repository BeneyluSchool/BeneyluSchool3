'use strict';

angular.module('bns.workshop.document.widgetGroupLockOverlay', [
  'bns.core.url',
  'bns.user.users',
  'bns.workshop.document.lockManager'
])

  .directive('bnsWorkshopDocumentWidgetGroupLockOverlay', function (url) {
    return {
      replace: true,
      templateUrl: url.view('workshop/document/directives/bns-workshop-document-widget-group-lock-overlay.html'),
    };
  })

;
