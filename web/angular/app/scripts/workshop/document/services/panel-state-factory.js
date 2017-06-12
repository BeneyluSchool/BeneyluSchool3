'use strict';

angular.module('bns.workshop.document.panelState', [])

  .factory('workshopDocumentPanelState', function () {
    var service = {
      expanded: true,
      large: false,
    };

    return service;
  })

;
