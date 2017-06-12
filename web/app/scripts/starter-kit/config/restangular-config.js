(function (angular) {
'use strict';

angular.module('bns.starterKit.restangularConfig', [
  'bns.starterKit.service',
])

  .config(StarterKitRestangularConfig)

;

function StarterKitRestangularConfig (RestangularProvider, starterKitProvider) {

  RestangularProvider.addFullRequestInterceptor(decorateRequestInterceptor);

  function decorateRequestInterceptor (element, operation, what, url, headers, params, httpConfig) {
    if (!(starterKitProvider.instance && starterKitProvider.instance.enabled)) {
      return; // starter kit not setup yet
    }

    return starterKitProvider.instance.interceptRequest(element, operation, what, url, headers, params, httpConfig);
  }

}

})(angular);
