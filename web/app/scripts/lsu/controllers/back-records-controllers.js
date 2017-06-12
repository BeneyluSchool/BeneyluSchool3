(function (angular) {
'use strict';

angular.module('bns.lsu.backRecordsControllers', [
  'bns.lsu.domains',
])

  .controller('LsuBackRecordsActionbar', LsuBackRecordsActionbarController)
  .controller('LsuBackRecordsSidebar', LsuBackRecordsSidebarController)
  .controller('LsuBackRecordsContent', LsuBackRecordsContentController)
  .controller('LsuBackRecordEditContent', LsuBackRecordEditContentController)
  .factory('lsuBackRecordsState', LsuBackRecordsStateFactory)

;

function LsuBackRecordsActionbarController (_, Routing, $scope, Restangular, bnsLoader, toast, lsuBackRecordsState) {

  var shared = $scope.shared = lsuBackRecordsState;
  var ctrl = this;
  ctrl.loader = bnsLoader;
  ctrl.canValidate = false;
  ctrl.validateRecord = validateRecord;
  ctrl.getRecordExportUrl = getRecordExportUrl;

  setupWatchers();

  function setupWatchers () {
    $scope.$watch('shared.positions', updateCanValidate, true);
    $scope.$watch('shared.domains', updateCanValidate);
    $scope.$watch('shared.commons', updateCanValidate);
    $scope.$watch('shared.user', updateCanValidate);
    $scope.$watch('shared.template', updateCanValidate);
    $scope.$watch('shared.form.$valid', updateCanValidate);
  }

  function updateCanValidate () {
    ctrl.canValidate = false;
    if (!(shared.positions && shared.user && shared.template && shared.form)) {
      return;
    }
    ctrl.canValidate = shared.form.$valid && isCommonComplete() && (shared.template.is_cycle_end || isDomainComplete());
  }

  function isCommonComplete () {
    if (!(shared.commons && shared.positions)) {
      return false;
    }

    return _.every(shared.commons, function (commonDomain) {
      return !!shared.positions[commonDomain.id];
    });
  }

  function isDomainComplete () {
    if (!(shared.domains && shared.positions)) {
      return false;
    }

    var positionedDomains = 0;
    _.forEach(shared.domains, function (domain) {
      if (shared.positions[domain.id]) {
        positionedDomains++;
      } else if (domain.subdomains) {
        for (var i = 0; i < domain.subdomains.length; i++) {
          if (shared.positions[domain.subdomains[i].id]) {
            positionedDomains++;
            break;
          }
        }
      }
    });

    return positionedDomains >= 3;
  }

  function validateRecord (value) {
    if (!(shared.record && shared.template)) {
      return;
    }
    value = !!value;

    return bnsLoader.observePromise(
      Restangular.one('lsu', shared.record.id).patch({validated: value})
        .then(success)
        .catch(error)
    );
    function success () {
      shared.record.validated = value;
      shared.template.completion += value ? 1 : -1;
      if (!shared.template.user_completions[shared.record.user.id]) {
        shared.template.user_completions[shared.record.user.id] = {};
      }
      shared.template.user_completions[shared.record.user.id].validated = value;
      toast.success('LSU.FLASH_'+(value?'':'IN')+'VALIDATE_RECORD_SUCCESS');
    }
    function error (response) {
      toast.error('LSU.FLASH_'+(value?'':'IN')+'VALIDATE_RECORD_ERROR');

      throw response;
    }
  }

  function getRecordExportUrl (type, record) {
    return Routing.generate('bns_app_lsu_export_'+type, {
      ids: record.id,
    });
  }

}

function LsuBackRecordsSidebarController (_, $scope, $state, $stateParams, Restangular, CollectionMap, bnsLoader, lsuDomains, lsuBackRecordsState) {

  var shared = $scope.shared = lsuBackRecordsState;
  shared.selectedUsersMap = new CollectionMap([]);
  var ctrl = this;
  ctrl.editUser = editUser;
  ctrl.toggleAll = toggleAll;
  ctrl.toggle = toggle;
  ctrl.isAllSelected = false;

  init();

  function init () {
    shared.template = null;
    shared.config = null;
    shared.level = null;
    shared.domains = null;
    shared.users = null;

    loadTemplateAndUsers()
      .then(function () {
        loadDomains();
        loadCommonGround();
      })
    ;

    setupWatchers();
  }

  function setupWatchers () {
    $scope.$watch('shared.positions', updatePositions, true);
  }

  function loadTemplateAndUsers () {
    return bnsLoader.observePromise(
      Restangular.one('lsu/templates', $stateParams.templateId).get()
        .then(success),
      false
    );

    function success (template) {
      shared.template = template;
      shared.config = template.lsu_config;
      shared.level = template.lsu_config.lsu_level;

      return Restangular.one('lsu/configs', template.lsu_config.id).get()
        .then(function (config) {
          shared.users = config.users;
        })
      ;
    }
  }

  function loadDomains () {
    return bnsLoader.observePromise(
      lsuDomains.getByCycle(shared.level.cycle)
        .then(success),
      false
    );

    function success (domains) {
      shared.domains = domains;
    }
  }

  function loadCommonGround () {
    return bnsLoader.observePromise(
      lsuDomains.getByCycle('socle')
        .then(success),
      false
    );

    function success (domains) {
      shared.commons = domains;
    }
  }

  function editUser (user) {
    return $state.go('^.edit', {userId: user.id});
  }

  function updatePositions () {
    if (!(shared.positions && shared.user && shared.template)) {
      return;
    }

    var count = _.filter(shared.positions).length; // non-null positions
    if (!shared.template.user_completions[shared.user.id]) {
      shared.template.user_completions[shared.user.id] = {};
    }
    shared.template.user_completions[shared.user.id].positions = count;
  }

  function toggleAll () {
    if (ctrl.isAllSelected) {
      shared.selectedUsersMap.removec(shared.users);
    } else {
      shared.selectedUsersMap.addc(shared.users);
    }
    refreshIsAllSelected();
  }

  function toggle (user) {
    shared.selectedUsersMap.toggle(user);
    refreshIsAllSelected();
  }

  function refreshIsAllSelected () {
    ctrl.isAllSelected = _.every(shared.users, function (user) {
      return shared.selectedUsersMap.has(user);
    });
  }

}

function LsuBackRecordsContentController (_, Routing, $scope, $state, lsuBackRecordsState) {

  var shared = $scope.shared = lsuBackRecordsState;
  var ctrl = this;
  ctrl.editUser = editUser;
  ctrl.getExportUrl = getExportUrl;

  setupWatchers();

  function setupWatchers () {
    $scope.$watch('shared.user', updatePrevNext);
    $scope.$watch('shared.users', updatePrevNext);
  }

  function updatePrevNext () {
    ctrl.prev = ctrl.next = null;

    if (!(shared.users && shared.user)) {
      return;
    }

    var currentIndex = _.findIndex(shared.users, { id: shared.user.id });
    if (currentIndex > 0) {
      ctrl.prev = shared.users[currentIndex - 1];
    }
    if (currentIndex < shared.users.length - 1) {
      ctrl.next = shared.users[currentIndex + 1];
    }
  }

  function editUser (user) {
    if (!user) {
      return;
    }

    return $state.go('^.edit', {userId: user.id});
  }

  function getExportUrl (type, users) {
    var route = Routing.generate('bns_app_lsu_export_'+type, {
      templateId: shared.template.id,
      userIds: '__USER_IDS__',
    });

    // insert ids after routing, to avoid comma encoding
    return route.replace('__USER_IDS__', _.map(users, 'id'));
  }

}

function LsuBackRecordEditContentController (_, $rootScope, $scope, $timeout, $stateParams, $mdUtil, Restangular, bnsLoader, dialog, toast, lsuBackRecordsState) {

  var shared = $scope.shared = lsuBackRecordsState;
  shared.positions = null; // reset positions before loading user
  shared.user = null;
  shared.record = null;
  shared.form = null;

  var ctrl = this;
  ctrl.comments = null;
  ctrl.model = {};

  init();

  function init () {
    loadRecord()
      .then(setupWatchers)
    ;
  }

  function loadRecord () {
    return bnsLoader.observePromise(
      Restangular.one('lsu/templates', $stateParams.templateId).one('users', $stateParams.userId).get()
        .then(success)
        .catch(error),
      false
    );

    function success (record) {
      shared.user = record.user;
      shared.record = record;
      ctrl.model = {
        accompanyingCondition: record.accompanying_condition,
        accompanyingConditionOther: record.accompanying_condition_other,
        globalEvaluation: record.global_evaluation,
        data: record.data,
      };
    }
    function error () {
      toast.error('LSU.FLASH_GET_RECORD_ERROR');
    }
  }

  function setupWatchers () {
    // Setup regular scope watchers, and deregister them as soon as the state
    // has changed, preventing them to fire in the stale scope.
    var deregistrers = [];
    deregistrers.push($scope.$watch('ctrl.comments', $mdUtil.debounce(handleCommentsUpdate, 1000), true));
    deregistrers.push($scope.$watch('shared.positions', $mdUtil.debounce(handlePositionsUpdate, 1000), true));
    deregistrers.push($scope.$watch('ctrl.model', $mdUtil.debounce(handleModelUpdate, 1000), true));
    deregistrers.push($rootScope.$on('user.updated', handleUpdatedUser));
    deregistrers.push($rootScope.$on('$stateChangeSuccess', function () {
      angular.forEach(deregistrers, function (deregistrer) {
        deregistrer();
      });
    }));
  }

  function handleCommentsUpdate (newComments, oldComments) {
    if (!(newComments && oldComments && newComments !== oldComments)) {
      return;
    }

    var data = {
      lsuComments: _.map(ctrl.comments, function (comment, domainId) {
        return {
          lsuDomain: domainId,
          comment: comment,
        };
      }),
    };

    return updateRecord(data);
  }

  function handlePositionsUpdate (newPositions, oldPositions) {
    if (!(newPositions && oldPositions && newPositions !== oldPositions)) {
      return;
    }

    var data = {
      lsuPositions: _.map(shared.positions, function (position, domainId) {
        return {
          lsuDomain: domainId,
          achievement: position,
        };
      }),
    };

    return updateRecord(data);
  }

  function handleModelUpdate (newModel, oldModel) {
    if (!(newModel && oldModel && newModel !== oldModel)) {
      return;
    }

    return updateRecord(newModel);
  }

  function updateRecord (data) {
    return bnsLoader.observePromise(Restangular.one('lsu', shared.record.id).patch(data)
      .catch(error)
    );
    function error () {
      toast.error('LSU.FLASH_UPDATE_RECORD_ERROR');
    }
  }

  function handleUpdatedUser ($event, user) {
    var localUser = _.find(shared.users, { id: user.id });
    if (localUser) {
      angular.extend(localUser, user);
    }
    if (shared.user.id === user.id) {
      angular.extend(shared.user, user);
    }
  }

}

function LsuBackRecordsStateFactory () {

  return {
    template: null,
    config: null,
    user: null,
  };

}

})(angular);
