(function (angular) {
'use strict';

angular.module('bns.lsu.backConfigControllers', [])

  .controller('LsuBackConfigContent', LsuBackConfigContentController)

;

function LsuBackConfigContentController (_, $q, $timeout, $scope, $mdUtil, Restangular, toast, navbar) {

  var ctrl = this;
  ctrl.group = null;
  ctrl.remainingPupils = [];
  ctrl.allLevels = [];
  ctrl.levels = [];
  ctrl.sortableConfig = {
    sort: false,
    group: 'config',
  };
  ctrl.pendingCount = 0;
  ctrl.successCount = 0;
  ctrl.disableLevelsCount = 0;
  ctrl.hasInit = false;
  ctrl.hasLockedLevels = false;

  var debounceHandleConfigsUpdate = $mdUtil.debounce(handleConfigsUpdate, 1000);

  init();

  function init () {
    return $q.all([
      loadAllLevels(),
      loadConfigs(),
    ])
      .finally(function end () {
        ctrl.hasInit = true;
        setupWatchers();
        setupLockedLevels();
      })
    ;
  }

  function loadAllLevels () {
    ctrl.pendingCount++;

    return Restangular.all('lsu').all('levels').getList()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (levels) {
      angular.forEach(levels, function (level) {
        level.value = level.id;
      });
      ctrl.allLevels = levels;
    }
    function error (response) {
      toast.error('LSU.FLASH_GET_LEVELS_ERROR');

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

        return group.all('lsu').all('configs').get('', {with_new: 1})
          .then(success)
          .catch(error)
          .finally(end)
        ;
      })
    ;
    function success (data) {
      ctrl.configs = data.configs;
      ctrl.remainingPupils = data.new_users || [];
      angular.forEach(ctrl.configs, function (config) {
        ctrl.levels.push(config.lsu_level.id);
      });
    }
    function error (response) {
      toast.error('LSU.FLASH_GET_CONFIGS_ERROR');

      throw response;
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function setupWatchers () {
    $scope.$watch('ctrl.configs', debounceHandleConfigsUpdate, true);
    $scope.$watchCollection('ctrl.levels', handleLevelsUpdate);
    $scope.$watch('ctrl.successCount', handleSuccessCountUpdate);

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

  function setupLockedLevels () {
    angular.forEach(ctrl.configs, function (config) {
      var level = _.find(ctrl.allLevels, { id: config.lsu_level.id });
      if (level && config.count_lsu_templates) {
        level.disabled = true;
        ctrl.hasLockedLevels = true;
      }
    });
  }

  function handleConfigsUpdate (newConfigs, oldConfigs) {
    if (!(newConfigs && oldConfigs)) {
      return; // false positives during init
    }

    angular.forEach(newConfigs, function (newConfig) {
      var oldConfig = _.find(oldConfigs, {id: newConfig.id});
      if (!oldConfig) {
        return;
      }
      var newIds = _.map(newConfig.users, 'id');
      var oldIds = _.map(oldConfig.users, 'id');
      if (!(_.difference(newIds, oldIds).length || _.difference(oldIds, newIds).length)) {
        return;
      }

      return updateConfig(newConfig);
    });
  }

  function updateConfig (config) {
    ctrl.pendingCount++;

    return Restangular.one('lsu/configs', config.id).patch({
      user_ids: _.map(config.users, 'id'),
    })
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success () {
      ctrl.successCount++;
    }
    function error (response) {
      toast.error('LSU.FLASH_UPDATE_CONFIG_ERROR');
      throw response;
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function handleLevelsUpdate (newLevels, oldLevels) {
    if (!(newLevels && oldLevels)) {
      return; // false positives during init
    }

    var added = _.difference(newLevels, oldLevels);
    var removed = _.difference(oldLevels, newLevels);
    angular.forEach(added, function (level) {
      var config = _.find(ctrl.configs, function (cfg) {
        return cfg.lsu_level.id === level;
      });
      if (config) {
        return;
      }
      addConfig(level);
    });
    angular.forEach(removed, function (level) {
      var config = _.find(ctrl.configs, function (cfg) {
        return cfg.lsu_level.id === level;
      });
      if (!config) {
        return;
      }
      removeConfig(config);
    });

  }

  function addConfig (level) {
    ctrl.pendingCount++;
    ctrl.disableLevelsCount++;

    return ctrl.group.all('lsu').all('configs').post({
      lsuLevel: level,
    })
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (config) {
      ctrl.configs.push(config);
      ctrl.successCount++;
    }
    function error (response) {
      toast.error('LSU.FLASH_CREATE_CONFIG_ERROR');

      // remove the level we tried to add
      _.remove(ctrl.levels, function (l) { return l === level; });

      throw response;
    }
    function end () {
      ctrl.pendingCount--;
      ctrl.disableLevelsCount--;
    }
  }

  function removeConfig (config) {
    ctrl.pendingCount++;
    ctrl.disableLevelsCount++;

    return Restangular.one('lsu/configs', config.id).remove()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success () {
      ctrl.successCount++;
      _.remove(ctrl.configs, config);
      angular.forEach(config.users, function (user) {
        ctrl.remainingPupils.push(user);
      });
    }
    function error (response) {
      toast.error('LSU.FLASH_REMOVE_CONFIG_ERROR');

      // restore the level we tried to remove
      ctrl.levels.push(config.lsu_level.id);

      throw response;
    }
    function end () {
      ctrl.pendingCount--;
      ctrl.disableLevelsCount--;
    }
  }

}

})(angular);
