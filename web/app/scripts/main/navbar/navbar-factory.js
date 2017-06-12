(function (angular) {
'use strict'  ;

angular.module('bns.main.navbar')

  .factory('navbar', NavbarFactory)

;

/**
 * @ngdoc service
 * @name navbar
 * @module bns.main.navbar
 *
 * @description
 * Holds navbar data.
 *
 * ** Attributes **
 *  - app: the current app
 *  - group: the current group
 *  - apps: collection of the user apps
 *  - hasHelp: whether online help is activated (defaults to false)
 *
 * ** Methods **
 *  - `setApp(app)` : sets the current app. If string is given, loads app data
 *                    from current group API before setting it
 *  - `setGroup(group)` : sets the current group, persisted through the API
 *  - `getApps()` : gets user apps
 *  - `getOrRefreshGroup()` : gets the current group (from API if not cached)
 *
 * @requires $rootScope
 * @requires $q
 * @requires $log
 * @requires Users
 * @requires Groups
 */
function NavbarFactory ($rootScope, $q, $log, Users, Groups) {

  var navbar = {
    // Navbar service is disabled by default. Enabled by the navbar directive
    // when present.
    enabled: false,
    mode: null,
    app: null,
    group: null,
    apps: [],
    hasHelp: false,
    shown: undefined, // for proper initial sync with user conf
    setApp: setApp,
    setGroup: setGroup,
    getApps: getApps,
    getOrRefreshGroup: getOrRefreshGroup,
    canAccess: canAccess,
    show: function () {
      this.shown = true;
    },
    hide: function () {
      this.shown = false;
    },
    toggle: function () {
      this.shown = !this.shown;
    },
  };

  return navbar;

  function getApps (id) {
    return Groups.getApplications(id).then(function (apps) {
      navbar.apps = apps;
    });
  }

  function setApp (app) {
    if (!navbar.enabled) {
      return $log.warn('Cannot set app without navbar');
    }

    if (angular.isString(app)) {
      // set app from unique_name: get info from current group
      return navbar.getOrRefreshGroup().then(function (group) {
        return group.all('applications').one(app).get()
          .then(success)
          .catch(error)
        ;
      });
    } else {
      // assume given app is valid: use it
      return $q(function (resolve) {
        resolve(success(app));
      });
    }

    function success (app) {
      return (navbar.app = app);
    }
    function error () {
      throw '[Error] navbar';
    }
  }

  function setGroup (group) {
    if (group.id === navbar.group.id) {
      return $q(function (resolve) {
        resolve(success());
      });
    } else {
      return Groups.setCurrent(group)
        .then(success)
        .catch(error)
      ;
    }
    function success () {
      return (navbar.group = group);
    }
    function error () {
      throw '[Error] navbar';
    }
  }

  function getOrRefreshGroup () {
    if (!navbar.enabled) {
      return $log.warn('Cannot get current group without navbar');
    }

    return Groups.getCurrent()
      .then(function success (group) {
        return (navbar.group = group);
      })
    ;
  }

  function canAccess (app) {
    // TODO: fix app.is_open
    return app.has_access_front || app.has_access_back;
  }

}

})(angular);
