'use strict';

angular.module('bns.user.users', [
  'restangular',
  'bns.components.toast',
  'bns.user.groups',
])

  /**
   * @ngdoc service
   * @name bns.user.users.Users
   * @kind function
   *
   * @description
   * A Restangular wrapper for the 'users' API, mith minor additions:
   *
   * ** Methods **
   * - `me()`: Gets the currently authenticated user, as a promise. Result is
   *           cached so that subsequent calls are instant (but still promises).
   * - `hasCurrentRight(right)`: Checks if user has the given right in the
   *                             current group. Returns a promise receiving a
   *                             boolean value.
   *
   * @requires Restangular
   * @requires toast
   * @requires Groups
   *
   * @returns {Object} The Users service
   */
  .factory('Users', function ($q, Restangular, toast, Groups) {
    var service = Restangular.service('users');
    service._me = null;
    service.me = me;
    service.hasRight = hasRight;
    service.hasCurrentRight = hasCurrentRight;

    var _me;

    return service;

    function me () {
      if (!_me) {
        _me = service.one('me', '').get();
        _me.catch(function error (response) {
          toast.error('USER.GET_ME_ERROR');
          console.error('[GET users/me]', response);
        });
      }

      return _me;
    }

    /**
     * Checks whether the user has the given right. If an optional group is
     * provided, checks if right exists in this group specifically. Else, checks
     * in any group.
     *
     * @param {String} right The right name to check
     * @param {Group|Integer} group The group where to check, or its id.
     *                              Optional.
     * @returns {Promise} A promise resolved with a boolean
     *
     * @example
     * Users.hasRight('SOME_PERMISSION')
     *   .then(callbackIfHasright)
     *   .catch(callbackIfHasNotRight)
     * ;
     */
    function hasRight (right, group) {
      var route = service.one('rights').one(right);
      if (group) {
        route = route.one('groups', group.id || group);
      }

      return route.get()
        .then(function success (result) {
          if (result.has_right) {
            return true;
          }

          // no right, break the promise chain
          return $q.reject(false);
        })
      ;
    }

    /**
     * Checks if the user has the given right in the current group.
     *
     * @param {String} right
     * @returns {Promise}
     */
    function hasCurrentRight (right) {
      return Groups.getCurrent()
        .then(function getCurrentGroupSuccess (group) {
          return service.hasRight(right, group);
        })
      ;
    }

  })

;
