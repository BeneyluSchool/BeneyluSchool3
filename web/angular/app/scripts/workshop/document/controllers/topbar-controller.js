'use strict';

angular.module('bns.workshop.document.topbarController', [
  'bns.core.url',
])

.controller('WorkshopDocumentTopbarController', function (document, URL_BASE) {
  var ctrl = this;

  ctrl.document = document;
  ctrl.exportUrl = URL_BASE + '/../atelier/document/' + document.id + '/export';
});
