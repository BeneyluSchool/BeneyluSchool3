(function (angular) {
'use strict';

angular.module('bns.main.apps')

  .factory('appsManager', AppsManagerFactory)

;

var TYPE_APPLICATIONS = 'applications';

function AppsManagerFactory (Groups) {

  var appsManager = {
    get: get,
    toggle: toggle,
    uninstall: uninstall,
    toggleFavorite: toggleFavorite,
  };

  return appsManager;

  function get (name, groupId, type) {
    type = normalizeType(type);

    return Groups.one(groupId).one(type, name).get();
  }

  /**
   * Toggles the open/close status of the given app in the given group
   *
   * @param {Object} app
   * @param {Integer} groupId
   * @param {String} type
   * @returns {Object} A promise of the API call
   */
  function toggle (app, groupId, type, userRole) {
    type = normalizeType(type);

    var status;
    if (app.is_open) {
      status = 'close';
    } else {
      status = 'open';
    }

    return Groups.one(groupId).one(type, app.unique_name).all(status).patch({userRole: userRole})
      .then(success)
      .catch(error)
    ;
    function success (response) {
      return response;
    }
    function error (response) {
      console.error('[PATCH]', app.unique_name, response);
      throw 'APPS.APP_TOGGLE_ERROR';
    }
  }

  /**
   * Uninstalls the given app in the given group
   *
   * @param {Object} app
   * @param {Integer} groupId
   * @param {String} type
   * @returns {Object} A promise of the API call
   */
  function uninstall (app, groupId, type) {
    type = normalizeType(type);

    return Groups.one(groupId).one(type, app.unique_name).all('uninstall').patch()
      .then(success)
      .catch(error)
    ;
    function success (response) {
      return response;
    }
    function error (response) {
      console.error('[PATCH]', app.unique_name, response);
      throw 'APPS.APP_UNINSTALL_ERROR';
    }
  }

  function toggleFavorite (app, groupId) {
    var newState = app.is_favorite ? 'false' : 'true';

    return Groups.one(groupId).one(TYPE_APPLICATIONS, app.unique_name).one('favorite', newState).patch()
      .then(success)
      .catch(error)
    ;

    function success (response) {
      app.is_favorite = !app.is_favorite;

      return response;
    }

    function error (response) {
      console.error('[PATCH]', app.unique_name, response);
      throw 'APPS.APP_TOGGLE_FAVORITE_ERROR';
    }
  }

  function normalizeType (type) {
    if (!type) {
      return TYPE_APPLICATIONS;
    }

    return type.toLowerCase();
  }

}

})(angular);
