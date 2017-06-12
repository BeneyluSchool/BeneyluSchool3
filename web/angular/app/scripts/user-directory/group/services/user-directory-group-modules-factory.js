'use strict';

angular.module('bns.userDirectory.group.modules', [
  'bns.core.message',
  'bns.userDirectory.restangular',
])

  .factory('userDirectoryGroupModules', function (message, UserDirectoryRestangular) {
    var srvc = {
      _modules: {},
      get: get,
      toggle: toggle,
    };

    function get (group) {
      if (!srvc._modules[group.id]) {
        srvc._modules[group.id] = UserDirectoryRestangular
          .one('groups', group.id)
          .all('modules')
          .getList()
        ;
      }

      return srvc._modules[group.id];
    }

    function toggle (group, moduleName, userRole) {
      var data = {
        role: userRole,
      };

      return UserDirectoryRestangular
        .one('groups', group.id)
        .one('modules', moduleName)
        .one('toggle')
        .get(data)
        .then(function success (module) {
          if (module.state) {
            message.success('USER_DIRECTORY.GROUP.ACTIVATE_MODULE_SUCCESS');
          } else {
            message.success('USER_DIRECTORY.GROUP.DEACTIVATE_MODULE_SUCCESS');
          }

          return module;
        })
        .catch(function error (response) {
          message.error('USER_DIRECTORY.GROUP.MANAGE_MODULE_ERROR');
          console.error('[GET groups/modules/toggle]', response);

          throw response;
        })
      ;
    }

    return srvc;
  })

;
