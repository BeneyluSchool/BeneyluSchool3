(function (angular) {
'use strict';

angular.module('bns.lsu.printController', [])

  .controller('LsuPrint', LsuPrintController)

;

function LsuPrintController ($q, $timeout, $window, $stateParams, Restangular, toast, lsuDomains) {

  var ctrl = this;
  ctrl.domainsByCycle = {};

  init();

  function init () {
    return loadRecords()
      .then(function () {
        $q.all([
          loadCommons(),
          loadDomains(),
        ])
          // one everything is ready, setup status, for PDF exports
          .then(function () {
            $timeout(function () {
              $window.status = 'done';
            }, 2000);
          })
        ;
      })
    ;
  }

  function loadRecords () {
    return Restangular.all('lsu').all('lookup').getList({
      template_id: $stateParams.templateId,
      user_ids: $stateParams.userIds,
      ids: $stateParams.ids,
    })
      .then(function success (records) {
        return (ctrl.records = records);
      })
      .catch(function error () {
        toast.error('LSU.FLASH_LOAD_RECORDS_ERROR');
      })
    ;
  }

  function loadCommons () {
    return lsuDomains.getByCycle('socle')
      .then(success)
    ;

    function success (domains) {
      ctrl.commons = domains;
    }
  }

  function loadDomains () {
    var cyclesDone = {};
    angular.forEach(ctrl.records, function (record) {
      var cycle = record.lsu_template.lsu_config.lsu_level.cycle;
      if (cyclesDone[cycle]) {
        return;
      }

      cyclesDone[cycle] = lsuDomains.getByCycle(cycle).then(function (domains) {
        ctrl.domainsByCycle[cycle] = domains;
      });
    });
  }

}

})(angular);
