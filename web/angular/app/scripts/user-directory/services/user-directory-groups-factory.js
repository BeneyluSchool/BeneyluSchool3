'use strict';

angular.module('bns.userDirectory.groups', [
  'bns.userDirectory.restangular',
  'bns.userDirectory.state',
  'bns.core.objectHelpers',
  'bns.core.treeUtils',
  'bns.core.message',
])

  /**
   * @ngdoc service
   * @name bns.userDirectory.groups.userDirectoryGroups
   * @kind function
   *
   * @description
   * Manager of the user directory group data. Handles model operations
   *
   * @returns {Object} The userDirectoryGroups service
   */
  .factory('userDirectoryGroups', function userDirectoryGroups (_, objectHelpers, treeUtils, message, UserDirectoryRestangular) {
    var VIEW_DEFAULT = '';

    var service = {
      _groups: null,
      _view: VIEW_DEFAULT,
      _groupsByView: {},
      _groupCache: {},
      view: view,
      load: load,
      getList: getList,
      get: get,
      lookup: lookup,
      hasUser: hasUser,
      create: create,
      remove: remove,
      update: update,
      addUsers: addUsers,
      removeUsers: removeUsers,
      reset: reset,
    };

    service.view(VIEW_DEFAULT);

    return service;

    /**
     * Gets the list of groups, eventually already cached, as a promise
     *
     * @returns {Object} A promise
     */
    function getList () {
      if (!service._groups) {
        service._groups = service.load();
      }

      return service._groups;
    }

    /**
     * Gets a group based on its ID
     *
     * @param {Integer} id
     * @returns {Object} A promise
     */
    function get (id) {
      id = parseInt(id, 10);
      if (service._groupCache[id]) {
        return service._groupCache[id];
      }

      var params = {
        view: service._view,
      };

      var group = UserDirectoryRestangular.one('groups', id).get(params);

      group.catch(function error (response) {
        message.error('USER_DIRECTORY.GET_GROUPS_ERROR');
        console.error('[GET group]', response);
      });

      service._groupCache[id] = group;

      return group;
    }

    /**
     * Gets a collection of groups based on their IDs
     *
     * @param {Array} ids
     * @returns {Object} A promise
     */
    function lookup (ids) {
      return service.getList().then(function (groups) {
        var found = [];
        angular.forEach(ids, function (id) {
          var item = treeUtils.find(groups, {id: id}, '_embedded.subgroups');
          if (item) {
            found.push(item);
          }
        });

        return found;
      });
    }

    function view (v) {
      if (v || v === VIEW_DEFAULT) {
        service._view = v;
        service._groups = null;
        service._groupCache = {};
      } else {
        return service._view;
      }
    }

    /**
     * Loads the list of groups from API
     *
     * @returns {Object} The list of group promise
     */
    function load () {
      if (service._groupsByView[service._view]) {
        return service._groupsByView[service._view];
      }

      var params = {
        view: service._view,
      };

      var groups = UserDirectoryRestangular.all('groups').getList(params);

      groups.catch(function error (response) {
        message.error('USER_DIRECTORY.GET_GROUPS_ERROR');
        console.error('[GET groups]', response);
      });

      service._groupsByView[service._view] = groups;

      return groups;
    }

    /**
     * Checks if given group contains the given user
     *
     * @param {Object} group
     * @param {Object} user
     * @returns {Boolean}
     */
    function hasUser (group, user) {
      var users = group._embedded.users;
      for (var role in users) {
        if (users.hasOwnProperty(role)) {
          if (users[role].indexOf(user.id) > -1) {
            return true;
          }
        }
      }

      return false;
    }

    /**
     * Creates a group based on the given required data:
     * - label: the group name
     * - parent: ID of the parent group
     *
     * @param  {Object} data Map of data
     * @returns {Object} A promise
     */
    function create (data) {
      if (!data.label) {
        return console.error('Cannot create group without label');
      }

      if (!data.parent) {
        return console.error('Cannot create group without parent');
      }

      var userIds = [];
      if (data.users) {
        angular.forEach(data.users, function (user) {
          userIds.push(user.id);
        });
      }

      // post data to actually create the group
      return UserDirectoryRestangular.one('groups', data.parent).post('team', {
        label: data.label,
        users: userIds,
      })
        .then(function success (response) {
          message.success('USER_DIRECTORY.ADD_WORKGROUP_SUCCESS');

          // fetch the new group from its url
          var getPromise = UserDirectoryRestangular.oneUrl('media-folder', response.headers.location).get();
          getPromise
            .then(insertGroup)
            .catch(function getError (response) {
              console.error('[GET group]', response);
            })
          ;

          // thenable that receives the group object as parameter
          return getPromise;
        })
        .catch(function error (response) {
          message.error('USER_DIRECTORY.ADD_WORKGROUP_ERROR');
          console.error('[POST team]', response);
          throw 'USER_DIRECTORY.ADD_WORKGROUP_ERROR';
        })
      ;
    }

    /**
     * Updates the given group with the given data.
     *
     * @param {Object} group
     * @param {Object} data API-compliant data structure
     * @returns {Object} A promise receiving the API response
     */
    function update (group, data) {
      return UserDirectoryRestangular.one('groups', group.id).patch(data)
        .then(function success () {
          message.success('USER_DIRECTORY.EDIT_WORKSPACE_SUCCESS');
          updateGroup(group, data);
        })
        .catch(function error (response) {
          message.error('USER_DIRECTORY.EDIT_WORKSPACE_ERROR');
          console.error('[PATCH group]', response);

          throw 'USER_DIRECTORY.EDIT_WORKSPACE_ERROR';
        })
      ;
    }

    /**
     * Removes the given group.
     *
     * @param {Object} group
     * @returns {Object} A promise that is given the deleted group
     */
    function remove (group) {
      return UserDirectoryRestangular.one('groups', group.id).remove()
        .then(function success () {
          message.success('USER_DIRECTORY.DELETE_WORKGROUP_SUCCESS');
          service.getList().then(function (groups) {
            treeUtils.remove(groups, { id: group.id }, '_embedded.subgroups');
          });

          return group;
        })
        .catch(function error (response) {
          message.error('USER_DIRECTORY.DELETE_WORKGROUP_ERROR');
          console.error('[DELETE group]', response);

          throw 'USER_DIRECTORY.DELETE_WORKGROUP_ERROR';
        })
      ;
    }

    /**
     * Adds the given users to the given group
     *
     * @param {Object} group
     * @param {Array} users
     * @returns {Object} A promise receiving the edited group
     */
    function addUsers (group, users) {
      var patchData = {
        users: _.map(users, 'id'),
      };

      return UserDirectoryRestangular.one('groups', group.id).all('users').all('add').patch(patchData)
        .then(function success (response) {
          objectHelpers.softMerge(group, response, 'id', false, false, true);

          return group;
        })
        .catch(function error (response) {
          message.error('USER_DIRECTORY.ADD_USERS_ERROR');
          console.error('[PATCH group/users/add]', response);

          throw 'USER_DIRECTORY.ADD_USERS_ERROR';
        })
      ;
    }

    /**
     * Removes the given users from the given group
     *
     * @param {Object} group
     * @param {Array} users
     * @returns {Object} A promise receiving the edited group
     */
    function removeUsers (group, users) {
      var patchData = {
        users: _.map(users, 'id'),
      };

      return UserDirectoryRestangular.one('groups', group.id).all('users').all('remove').patch(patchData)
        .then(function success (response) {
          objectHelpers.softMerge(group, response, 'id', false, false, true);

          return group;
        })
        .catch(function error (response) {
          message.error('USER_DIRECTORY.REMOVE_USERS_ERROR');
          console.error('[PATCH group/users/remove]', response);

          throw 'USER_DIRECTORY.REMOVE_USERS_ERROR';
        })
      ;
    }

    /**
     * Inserts the given group in the group tree, based on its parent id.
     *
     * @param {Object} group
     * @returns {Object}|{Boolean} The group if successful, false on failure
     */
    function insertGroup (group) {
      service.getList().then(function (groups) {
        var parentId = group.parent_id;
        var parent = treeUtils.find(groups, { id: parentId }, '_embedded.subgroups');
        if (!parent) {
          console.error('Could not find group for parent id', parentId);

          return false;
        }

        if (parent._embedded && parent._embedded.subgroups) {
          parent._embedded.subgroups.push(group);
        }

        return group;
      });
    }

    /**
     * Patches the given group with the given data. Structures should match...
     *
     * @param {Object} group
     * @param {Object} data
     */
    function updateGroup (group, data) {
      objectHelpers.softMerge(group, data);
    }

    /**
     * Resets this service's state
     */
    function reset () {
      service._groups = null;
      service._view = VIEW_DEFAULT;
    }
  })

;
