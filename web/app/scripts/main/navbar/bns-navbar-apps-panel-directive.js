(function (angular) {
'use strict';

angular.module('bns.main.navbar')

  .directive('bnsNavbarAppsPanel', BNSNavbarAppsPanelDirective)
  .controller('BNSNavbarAppsPanel', BNSNavbarAppsPanelController)

;

/**
 * @ngdoc directive
 * @name bnsNavbarAppsPanel
 * @module bns.main.navbar
 *
 * @description Shows a panel to manage apps in a group
 *
 * ** Attributes **
 *  - `group`: the current group
 *  - `mode`: the current view mode ('list', 'mgmt')
 *  - `type`: the type of apps to have ('applications', 'activities')
 *  - `init-when`: ng expression that, when provided, delays directive
 *                 initialization until it is truey
 *  - `with-favorites`: whether to include favorites/sort management
 */
function BNSNavbarAppsPanelDirective () {

  return {
    templateUrl: 'views/main/navbar/bns-navbar-apps-panel.html',
    scope: {
      group: '=',
      mode: '=',
      type: '@',
      initWhen: '=',
      withFavorites: '@',
    },
    controller: 'BNSNavbarAppsPanel',
    controllerAs: 'panel',
    bindToController: true,
  };

}

function BNSNavbarAppsPanelController (_, $scope, $attrs, $filter, $window, $translate, $mdSidenav, $mdUtil, $mdMedia, Routing, toast, global, navbar, Groups, Users) {

  var TYPE_APPLICATIONS = 'APPLICATIONS';
  var TYPE_ACTIVITIES = 'ACTIVITIES';
  var TYPES_SINGLE = {};
  TYPES_SINGLE[TYPE_APPLICATIONS] = 'APPLICATION';
  TYPES_SINGLE[TYPE_ACTIVITIES] = 'ACTIVITY';

  var store = {};

  var debouncedUpdateAppsSort = $mdUtil.debounce(updateAppsSort, 2500);

  var panel = this;
  panel.items = {};
  panel.isGroupMode = isGroupMode;
  panel.toggleMode = toggleMode;
  panel.selectApp = selectApp;
  panel.withFavorites = !!$scope.$eval($attrs.withFavorites);
  panel.sortableConfig = {
    handle: '.app-drag-handle',
    disabled: !panel.withFavorites,
    onUpdate: onSortUpdate,
  };

  lazyInit();

  // call actual directive initialization only when condition is met
  function lazyInit () {
    if ($attrs.initWhen) {
      var unwatch = $scope.$watch('panel.initWhen', function (canInit) {
        if (canInit) {
          unwatch();
          init();
        }
      });
    } else {
      init();
    }
  }

  function init () {
    panel.$mdMedia = $mdMedia;
    panel.logoutUrl = Routing.generate('disconnect_user');
    if ([TYPE_APPLICATIONS, TYPE_ACTIVITIES].indexOf(panel.type) === -1) {
      return console.warn('Unknown panel type:', panel.type);
    }

    panel.typeSingle = TYPES_SINGLE[panel.type];

    if (TYPE_APPLICATIONS !== panel.type) {
      // user has no non-apps items
      store.me = {};
    }

    $scope.navbar = navbar; // for access control
    $scope.$mdSidenav = $mdSidenav; // for sidebar toggle

    $scope.$watch('panel.group', loadGroupItems);

    $scope.$on('bns.app.uninstall', removeApp);
  }

  function loadGroupItems (group) {
    if (!group) {
      return;
    }

    if (store[group.id]) {
      panel.items = store[group.id];
    } else if ((group.id || group.all) && !group.page) {
      panel.busy = true;
      var promise;
      if ('me' === group.id) {
        promise = Users.one('me', '').all('applications').getList();
      } else if (TYPE_APPLICATIONS === panel.type) {
        promise = Groups.getApplications(group.id, true, true);
      } else {
        promise = group.all(panel.type.toLowerCase()).getList();
      }
      promise
        .then(function success (apps) {
          store[group.id] = { all: apps };
          store[group.id].manageable = $filter('filter')(store[group.id].all, { can_open: true });
          store[group.id].uninstallable = $filter('filter')(store[group.id].all, { is_uninstallable: true });
          store[group.id].privates = $filter('filter')(store[group.id].all, { is_private: true });
          store[group.id].partially = $filter('filter')(store[group.id].all, { is_partially_open: true });

          // inject a new fake app if spot is available
          var spot = getSpot(store[group.id].all);
          if (spot) {
            store[group.id].newApp = buildNewApp(spot);
          }


          if (global('cerise')) {
            store[group.id].cerise = buildCeriseApp();
          }

          panel.items = store[group.id];
        })
        .catch(function error (response) {
          if(404 === response.status) {
            panel.items = store[group.id];
          }
        })
        .finally(function end () {
          panel.busy = false;
        })
      ;
    }
  }

  function removeApp (event, app, groupId) {
    event.stopPropagation();
    removeAppFromGroup(app, store[groupId].all);
    removeAppFromGroup(app, store[groupId].manageable);
    removeAppFromGroup(app, store[groupId].uninstallable);

    function removeAppFromGroup (app, group) {
      var idx = group.indexOf(app);
      if (idx > -1) {
        group.splice(idx, 1);
      }
    }
  }

  function isGroupMode () {
    if (!panel.group.manageable) {
      return;
    }
    return (panel.group.type === 'ENVIRONMENT' || panel.group.type === 'CITY'|| panel.group.type === 'CIRCONSCRIPTION');
  }

  function toggleMode () {
    if (!panel.group.manageable) {
      return;
    }

    if (angular.isString(panel.group.manageable)) {
      // link to legacy app management pages, try to switch to correct context
      if (panel.group.parent_id) {
        return Groups.setCurrent(panel.group.parent_id).then(function success () {
          return redirect(panel.group.manageable);
        });
      }
      if (panel.group.partner_id) {
        return Groups.setCurrent(panel.group.partner_id).then(function success () {
          return redirect(panel.group.manageable);
        });
      }

      // fallback to a simple redirect
      return redirect(panel.group.manageable);
    }

    if ('list' === panel.mode) {
      panel.mode =  'mgmt';
    } else {
      panel.mode = 'list';
    }

    function redirect (url) {
      return ($window.location = url);
    }
  }

  function selectApp (app) {
    $scope.$emit('bns.navbar.app.selected', app);
  }

  function getSpot (items) {
    for (var i = 0; i < items.length; i++) {
      var app = items[i];
      if ('SPOT' === app.unique_name) {
        return app;
      }
    }

    return false;
  }

  function buildNewApp (spot) {
    var newApp = angular.copy(spot);
    newApp.label = $translate.instant('APPS.LABEL_NEW_' + panel.typeSingle);
    newApp.icon = 'newapp';
    newApp.spot_origin = 'add ' + panel.typeSingle.toLowerCase();
    delete newApp.is_private;

    return newApp;
  }

  function buildCeriseApp () {
    var cerise = {};
    cerise.unique_name = 'CERISE';
    cerise.link  = global('cerise');
    cerise.external = 'true';
    cerise.description = 'CERISE_ACCESS';
    cerise.label = 'Cerise Prim';
    cerise.has_access_front = true;
    cerise.is_private = true;


    return cerise;
  }

  function onSortUpdate (event) {
    debouncedUpdateAppsSort(event.models);
  }

  function updateAppsSort (apps) {

    return panel.group.one('applications', 'sort').patch({
      applications: _.map(apps, 'unique_name'),
    })
      .then(function success () {
        toast.success('APPS.FLASH_REORDER_APPS_SUCCESS');
        // update local ranks
        var rank = 0;
        angular.forEach(apps, function (app) {
          app.rank = rank++;
        });
      })
      .catch(function error (response) {
        console.error(response);
        toast.error('APPS.FLASH_REORDER_APPS_ERROR');
        throw response;
      })
    ;
  }

}

})(angular);
