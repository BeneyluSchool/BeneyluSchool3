(function (angular) {
'use strict';

angular.module('bns.lsu.frontRecordControllers', [])

  .controller('LsuFrontRecordActionbar', LsuFrontRecordActionbarController)
  .controller('LsuFrontRecordContent', LsuFrontRecordContentController)

;

function LsuFrontRecordActionbarController (Routing, record) {

  var ctrl = this;
  ctrl.record = record;
  ctrl.getExportPdfUrl = getExportPdfUrl;

  function getExportPdfUrl () {
    return Routing.generate('bns_app_lsu_export_pdf', {
      ids: ctrl.record.id,
    });
  }

}

function LsuFrontRecordContentController (_, lsuDomains, record) {

  var ctrl = this;
  ctrl.record = record;
  ctrl.template = record.lsu_template;
  ctrl.cycle = record.lsu_template.lsu_config.lsu_level.cycle;

  init();

  function init () {
    loadDomains();
    loadCommonGround();
  }

  function loadDomains () {
    return lsuDomains.getByCycle(ctrl.cycle)
      .then(success)
    ;
    function success (domains) {
      ctrl.domains = domains;
    }
  }

  function loadCommonGround () {
    return lsuDomains.getByCycle('socle')
      .then(success)
    ;

    function success (domains) {
      ctrl.commons = domains;
    }
  }

}

})(angular);
