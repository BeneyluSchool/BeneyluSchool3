'use strict';

angular.module('bns.userDirectory.users', [
  'bns.core.collectionMap',
  'bns.userDirectory.restangular',
])

  /**
   * @ngdoc service
   * @name bns.userDirectory.users.userDirectoryUsers
   * @kind function
   *
   * @description
   * Manager of the user directory group data. Handles model operations
   *
   * @returns {Object} The userDirectoryUsers service
   */
  .factory('userDirectoryUsers', function userDirectoryUsers ($q, CollectionMap, UserDirectoryRestangular) {
    var service = {
      usersCollection: new CollectionMap([]),
      lookup: lookup,
      reset: reset,
    };

    return service;

    function lookup (ids, view, group) {

      // check which users are already cached, and which need lookup
      var locals = [],
        remotes = [];
      angular.forEach(ids, function (id) {
        if (service.usersCollection.isset(id)) {
          locals.push(id);
        } else {
          remotes.push(id);
        }
      });

      // join local and remote calls
      return $q.all([getLocals(locals), getRemotes(remotes, view, group)]).then(function (data) {
        return data[0].concat(data[1]);
      });
    }

    // gets users stored in the local cache
    function getLocals (ids) {
      return $q.when(service.usersCollection.getc(ids));
    }

    // fetch remote users
    function getRemotes (ids, view, group) {
      if (!ids.length) {
        return $q.when([]);
      }

      return UserDirectoryRestangular.one('users', '').getList('lookup', {
        ids: ids.join(','),
        view: view,
        group_id: group ? group.id : null,
      })
        .then(function success (users) {
          // cache users
          angular.forEach(users, function (user) {
            service.usersCollection.add(user);
          });

          return users;
        })
        .catch(function error (response) {
          console.error('[GET users/lookup]', response);

          return [];
        })
      ;
    }

    /**
     * Resets this service's state
     */
    function reset () {
      service.usersCollection.reset();
    }
  })

;
