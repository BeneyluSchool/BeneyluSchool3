(function (angular) {
'use strict';

angular.module('bns.lsu.backTemplateEditControllers', [])

  .controller('LsuBackTemplateEditActionbar', LsuBackTemplateEditActionbarController)
  .controller('LsuBackTemplateEditContent', LsuBackTemplateEditContentController)
  .factory('lsuBackTemplateEditState', LsuBackTemplateEditStateFactory)

;

function LsuBackTemplateEditActionbarController ($scope, $state, Restangular, dialog, toast, lsuBackTemplateEditState) {

  var shared = $scope.shared = lsuBackTemplateEditState;
  var ctrl = this;
  ctrl.removeTemplate = removeTemplate;

  function removeTemplate (event) {
    if (!(shared.template && shared.template.id)) {
      return;
    }

    return dialog.confirm({
      targetEvent: event,
      intent: 'warn',
      title: 'LSU.TITLE_CONFIRM_DELETE_TEMPLATE',
      content: 'LSU.CONTENT_CONFIRM_DELETE_TEMPLATE',
      cancel: 'LSU.BUTTON_CANCEL',
      ok: 'LSU.BUTTON_CONFIRM_DELETE_TEMPLATE',
    })
      .then(doRemoveTemplate)
    ;

    function doRemoveTemplate () {
      return Restangular.one('lsu/templates', shared.template.id).remove()
        .then(success)
        .catch(error)
      ;
    }

    function success () {
      toast.success('LSU.FLASH_DELETE_TEMPLATE_SUCCESS');

      return $state.go('^');
    }
    function error () {
      toast.error('LSU.FLASH_DELETE_TEMPLATE_ERROR');
    }
  }

}

function LsuBackTemplateEditContentController (_, moment, $q, $timeout, $scope, $mdUtil, $state, $stateParams, Restangular, toast, navbar, lsuBackTemplateEditState) {

  var shared = $scope.shared = lsuBackTemplateEditState;
  var ctrl = this;
  ctrl.canBeCycleEnd = canBeCycleEnd;
  ctrl.hasNoConfig = false;
  ctrl.configHref = $state.href('app.lsu.back.config');
  ctrl.configs = [];       // available configs
  ctrl.pendingCount = 0;
  ctrl.successCount = 0;
  ctrl.version = 'v2016';  // TODO: get actual data

  init();

  function init () {
    return $q.all([
      loadConfigs(),
      loadTemplate(),
    ])
      .then(setupWatchers)
    ;
  }

  function setupWatchers () {
    $scope.$watch('ctrl.values', $mdUtil.debounce(handleTemplateUpdate, 1000), true);
    $scope.$watch('ctrl.values', $mdUtil.debounce(checkTemplateValidity, 200), true);
    $scope.$watch('ctrl.successCount', handleSuccessCountUpdate);
    $scope.$watchCollection('shared.template.template_domain_details', updateLockedLevels);

    var successCountUpdateTimeout;
    function handleSuccessCountUpdate (successCount) {
      $timeout.cancel(successCountUpdateTimeout);
      if (successCount) {
        successCountUpdateTimeout = $timeout(function () {
          ctrl.successCount = 0;
        }, 5000);
      }
    }
  }

  function loadDomains () {
    ctrl.pendingCount++;

    return Restangular.all('lsu').all('domains').getList({ cycle: shared.template.lsu_config.lsu_level.cycle, version: ctrl.version })
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (domains) {
      domains = _.sortBy(domains, 'tree_left');
      ctrl.suggestions = {};
      ctrl.domains = _.filter(domains, { tree_level: 2 });
      angular.forEach(ctrl.domains, function (domain) {
        domain.subdomains = [];
        domain.suggestions = [];
        var children = getChildren(domains, domain);
        angular.forEach(children, function (child) {
          var grandChildren = getChildren(domains, child);
          if (child.code) {
            domain.subdomains.push(child);
            child.suggestions = grandChildren;
          } else {
            domain.suggestions.push(child);
            angular.forEach(grandChildren, function (grandChild) {
              domain.suggestions.push(grandChild);
            });
          }
        });
      });

      function getChildren (tree, parent) {
        return _.filter(tree, function (node) {
          return node.tree_level === parent.tree_level + 1 && node.tree_left > parent.tree_left && node.tree_right < parent.tree_right;
        });
      }
    }
    function error (response) {
      toast.error('LSU.FLASH_LOAD_DOMAINS_ERROR');

      throw response;
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function loadConfigs () {
    ctrl.pendingCount++;

    return navbar.getOrRefreshGroup()
      .then(function (group) {
        ctrl.group = group;

        return group.all('lsu').all('configs').get('')
          .then(success)
          .catch(error)
          .finally(end)
        ;
      })
    ;

    function success (data) {
      ctrl.configs = [];
      angular.forEach(data.configs, function (config) {
        ctrl.configs.push({
          label: config.lsu_level.label,
          value: config.id,
          cycle: config.lsu_level.cycle,
          levelCode: config.lsu_level.code
        });
      });
      if (!ctrl.configs.length) {
        ctrl.hasNoConfig = true;
      }
    }
    function error (response) {
      toast.error('LSU.FLASH_GET_CONFIGS_ERROR');

      throw response;
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function loadTemplate () {
    if ($stateParams.id) {
      ctrl.pendingCount++;

      return Restangular.all('lsu').one('templates', $stateParams.id).get()
        .then(success)
        .catch(error)
        .finally(end)
      ;
    } else {
      ctrl.values = {
        config: null,
        period: '',
        started_at: null,
        ended_at: null,
        data: {},
        is_cycle_end: false,
      };
      shared.template = angular.extend({
        template_domain_details: []
      }, ctrl.values);
    }

    function success (template) {
      ctrl.values = {
        id: template.id,
        config: template.lsu_config.id,
        period: template.period,
        started_at: moment(template.started_at).toDate(),
        ended_at: moment(template.ended_at).toDate(),
        data: (template.data && !angular.isArray(template.data)) ? template.data : {},
        is_cycle_end: template.is_cycle_end,
      };
      shared.template = template;
      loadDomains();
    }
    function error (response) {
      toast.error('LSU.FLASH_GET_TEMPLATE_ERROR');

      throw response;
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function handleTemplateUpdate (newValues, oldValues) {
    if (!ctrl.isValid || newValues === oldValues || (newValues.id && !oldValues.id)) {
      return;
    }
    ctrl.pendingCount++;

    var data = {
      lsu_config: newValues.config,
      started_at: moment(newValues.started_at).format(),
      ended_at: moment(newValues.ended_at).format(),
      period: newValues.period,
      data: newValues.data,
      is_cycle_end: newValues.is_cycle_end,
    };
    var route;
    if (newValues.id) {
      route = Restangular.one('lsu/templates', newValues.id).patch(data);
      delete data.lsu_config;
    } else {
      route = ctrl.group.all('lsu').all('templates').post(data);
    }

    return route ? route
      .then(success)
      .catch(error)
      .finally(end)
    : '';
    function success (response) {
      ctrl.successCount++;
      if (response.id) {
        newValues.id = response.id;
        shared.template = response;
        loadDomains();
      }
      shared.template.period = data.period;
    }
    function error () {
      toast.error('LSU.FLASH_SAVE_TEMPLATE_ERROR');
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function checkTemplateValidity () {
    // TODO: constraints on dates
    ctrl.isValid = ctrl.form && ctrl.form.$valid && ctrl.values && ctrl.values.config;
  }

  function canBeCycleEnd () {
    if (!(ctrl.values && ctrl.values.config)) {
      return false;
    }
    var config = _.find(ctrl.configs, { value: ctrl.values.config });

    return config && config.levelCode === 'CE2';
  }

  function updateLockedLevels () {
    angular.forEach(ctrl.configs, function (config) {
      if (shared.template && shared.template.lsu_config) {
        config.disabled = (config.cycle !== shared.template.lsu_config.lsu_level.cycle);
      } else {
        config.disabled = false;
      }
    });
  }

}

function LsuBackTemplateEditStateFactory () {

  return {};

}

})(angular);
