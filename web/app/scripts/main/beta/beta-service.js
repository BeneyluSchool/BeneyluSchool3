(function (angular) {
'use strict';

angular.module('bns.main.beta.service', [
  'restangular',
])

  .factory('Beta', BetaFactory)

;

function BetaFactory (Restangular) {

  var cache = {};

  // for when response headers are needed
  var FullResponseRestangular = Restangular.withConfig(function (RestangularConfigurer) {
    RestangularConfigurer.setFullResponse(true);
  });

  return {
    get: get,
    toggle: toggle,
    getForGroup: getForGroup,
    setForGroup: setForGroup,
  };

  function get () {
    if (!cache._get) {
      cache._get = Restangular.one('users').one('me').one('beta').get();
    }

    return cache._get;
  }

  function toggle () {
    return get().then(function (beta) {
      return FullResponseRestangular.one('users').one('me').one('beta')
        .all(beta.beta_user ? 0 : 1)
        .patch()
        .then(function success (response) {
          // update cached/shared object
          if (204 === response.status) {
            beta.beta_user = !beta.beta_user;
          }

          return beta;
        })
      ;
    });
  }

  function getForGroup (groupId) {
    return Restangular.one('groups', groupId).one('beta').get();
  }

  function setForGroup (groupId, value) {
    return FullResponseRestangular.one('groups', groupId).one('beta')
      .all(value ? 1 : 0)
      .patch()
    ;
  }

}

})(angular);
