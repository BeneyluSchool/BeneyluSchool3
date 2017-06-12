(function (angular) {
'use strict';

angular.module('bns.lsu.backTemplatesControllers', [])

  .controller('LsuBackTemplatesSidebar', LsuBackTemplatesSidebarController)
  .controller('LsuBackTemplatesContent', LsuBackTemplatesContentController)
  .factory('lsuBackTemplatesState', LsuBackTemplatesStateFactory)

;

function LsuBackTemplatesSidebarController ($scope, lsuBackTemplatesState) {

  $scope.shared = lsuBackTemplatesState;

}

function LsuBackTemplatesContentController (_, Routing, $q, $scope, Restangular, toast, navbar, lsuBackTemplatesState) {

  var shared = $scope.shared = lsuBackTemplatesState;
  shared.allLevels = [];
  var ctrl = this;
  ctrl.busy = false;
  ctrl.hasNoTemplate = false;
  ctrl.copyTemplate = copyTemplate;
  ctrl.getExportXmlUrl = getExportXmlUrl;
  ctrl.getExportPdfUrl = getExportPdfUrl;

  init();

  function init () {
    ctrl.busy = true;

    return navbar.getOrRefreshGroup()
      .then(function (group) {
        return $q.all([
          loadTemplates(group),
          loadConfigs(group),
        ])
        .finally(function () {
          ctrl.busy = false;
        });
      })
    ;
  }

  function loadTemplates (group) {
    return group.all('lsu').all('templates').getList()
      .then(success)
      .catch(error)
    ;
    function success (templates) {
      ctrl.templatesByLevel = {};
      shared.allLevels = [];
      angular.forEach(templates, registerTemplate);
      sortTemplates();

      if (!templates.length) {
        ctrl.hasNoTemplate = true;
      }
    }
    function error (response) {
      if (404 === response.status) {
        ctrl.hasNoTemplate = true;

        return;
      }

      toast.error('LSU.FLASH_GET_TEMPLATES_ERROR');

      throw response;
    }
  }

  function loadConfigs (group) {
    return group.all('lsu').all('configs').get('')
      .then(success)
      .catch(error)
    ;
    function success (data) {
      ctrl.configsByLevel = {};
      angular.forEach(data.configs, function (config) {
        ctrl.configsByLevel[config.lsu_level.code] = config;
      });
    }
    function error (response) {
      toast.error('LSU.FLASH_GET_CONFIGS_ERROR');

      throw response;
    }
  }

  function copyTemplate (template) {
    ctrl.busy = true;

    return Restangular.one('lsu/templates', template.id).one('copy').post()
      .then(function success (template) {
        toast.success('LSU.FLASH_COPY_TEMPLATE_SUCCESS');
        registerTemplate(template);
        sortTemplates();
      })
      .catch(function error (response) {
        toast.error('LSU.FLASH_COPY_TEMPLATE_ERROR');

        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function getExportXmlUrl (template) {
    return Routing.generate('bns_app_lsu_export_xml', {templateId: template.id});
  }

  function getExportPdfUrl (template) {
    return Routing.generate('bns_app_lsu_export_pdf', {templateId: template.id});
  }

  function registerTemplate (template) {
    // persistent local variable to remember which levels are already parsed
    if (!registerTemplate.levelMap) {
      registerTemplate.levelMap = {};
    }

    if (!registerTemplate.levelMap[template.lsu_config.lsu_level.code]) {
      var level = angular.copy(template.lsu_config.lsu_level);
      level.value = level.code;
      shared.allLevels.push(level);
      registerTemplate.levelMap[template.lsu_config.lsu_level.code] = true;
      ctrl.templatesByLevel[level.code] = [];
    }
    ctrl.templatesByLevel[template.lsu_config.lsu_level.code].push(template);
    template.openManager = {
      getStatus: function getStatus () {
        return $q.when({ status: template.is_open });
      },
      toggle: function toggle (value) {
        return Restangular.one('lsu/templates', template.id).patch({
          is_open: !!value,
        })
          .then(function success () {
            return { status: !!value };
          })
        ;
      },
    };
  }

  function sortTemplates () {
    // sort templates by start date descending
    angular.forEach(ctrl.templatesByLevel, function (tpls, level) {
      ctrl.templatesByLevel[level] = _.sortByOrder(tpls, function (template) {
        return template.started_at;
      }, false);
    });
  }

}

function LsuBackTemplatesStateFactory () {

  return {
    allLevels: [],
    levels: [],
  };

}

})(angular);
