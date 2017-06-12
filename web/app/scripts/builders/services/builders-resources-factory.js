(function (angular) {
'use strict';

angular.module('bns.builders.resources', [])

  .constant('BUILDERS_CLOUD_BASE', 'https://storage.gra1.cloud.ovh.net/v1/AUTH_03caab6673f7495a9b7f2af36e8d748e/beneylu-public/builders')
  .factory('buildersResources', BuildersResourcesFactory)

;

function BuildersResourcesFactory (global, BUILDERS_CLOUD_BASE) {

  return {
    get: get,
  };

  function get (file) {
    var locale = global('locale') || 'fr';

    return BUILDERS_CLOUD_BASE + '/' + locale + '/' + file;
  }

}

})(angular);
