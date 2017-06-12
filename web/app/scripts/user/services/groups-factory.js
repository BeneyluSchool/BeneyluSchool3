'use strict';

angular.module('bns.user.groups', [
  'restangular',
  'bns.core.arrayUtils',
])

  /**
   * @ngdoc service
   * @name Groups
   * @module bns.user.groups
   * @kind function
   *
   * @description
   * A Restangular wrapper for the 'groups' API.
   *
   * @requires Restangular
   *
   * @returns {Object} The Users service
   */
  .factory('Groups', function (Restangular, arrayUtils) {
    var Groups = Restangular.service('groups');

    // internal stuff
    Groups._current = null;
    Groups._applications = {};

    /**
     * The current group
     *
     * @type {Object}
     */
    Groups.current = null;

    Groups.getCurrent = getCurrent;
    Groups.setCurrent = setCurrent;
    Groups.getApplications = getApplications;

    return Groups;

    /**
     * Gets the current group, reusing cached result of previous calls if any.
     *
     * @param {Boolean} force Whether to force a new API call, ignoring cache.
     *                        Defaults to false.
     * @returns {Object} A promise, receiving the current group
     */
    function getCurrent (force) {
      if (!Groups._current || force) {
        Groups._current = Groups.one('current', '').get();
        Groups._current.then(function success (group) {
          Groups.current = group;
        });
      }

      return Groups._current;
    }

    /**
     * Sets the given group as current
     *
     * @param {Object} group A group object, or its id.
     * @param {Boolean} force Whether to force a new API call if given group is
     *                        already current, voiding all caches. Defaults to
     *                        false
     * @returns {Object} A promise, receiving the group
     */
    function setCurrent (group, force) {
      var id = group.id || group;
      if (Groups.current && Groups.current.id === id && !force) {
        return Groups._current;
      }
      delete Groups._current;
      delete Groups.current;

      return Groups.one('current', '').patch({id: id})
        .then(success)
      ;
      function success (newGroup) {
        return (Groups.current = group.id ? group : newGroup);
      }
    }

    /**
     * Gets the application for the given group id. By default, results are
     * cached.
     *
     * @param {Integer} groupId The group id. If none provided, will fetch apps
     *                          of the current group.
     * @param {Boolean} force   Whether to force a new API call, ignoring cache.
     *                          Defaults to false.
     * @param {Boolean} replace Whether to replace previous results, but keeping
     *                          object reference.
     *                          Defaults to false (the new results override the
     *                          previous ones).
     * @returns {Object}        A promise, receiving the applications collection
     */
    function getApplications (groupId, force, replace) {
      var previousApps;

      // no group provided: get current group and try again
      if (!groupId) {
        return Groups.getCurrent().then(function (group) {
          return Groups.getApplications(group.id, force, replace);
        });
      }

      if (!Groups._applications[groupId] || force) {
        // keep a reference to previous results, before overriding the promise
        if (replace && Groups._applications[groupId] && Groups._applications[groupId].previous) {
          previousApps = Groups._applications[groupId].previous;
        }
        Groups._applications[groupId] = Groups.one(groupId).all('applications').getList();
        Groups._applications[groupId].then(success);
      }

      return Groups._applications[groupId];

      function success (apps) {
        apps.sort(function (a, b) {
          return a.rank - b.rank;
        });

        // replace previous reference array
        if (replace && previousApps) {
          arrayUtils.empty(previousApps);
          arrayUtils.merge(previousApps, apps);
          apps = previousApps;
        }

        return (Groups._applications[groupId].previous = apps);
      }
    }
  })

;
