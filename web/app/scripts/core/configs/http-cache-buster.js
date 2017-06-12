(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.httpCacheBuster
 *
 * @description
 * Invalidates browser cache according to the app version.
 */
angular.module('bns.core.httpCacheBuster', [
  'bns.core.parameters',
])

  .config(HttpCacheBusterConfig)
  .factory('cacheBusterInterceptor', CacheBusterInterceptorFactory)

;

function HttpCacheBusterConfig ($httpProvider) {

  $httpProvider.interceptors.push('cacheBusterInterceptor');

}

/**
 * @ngdoc service
 * @name cacheBusterInterceptor
 * @module bns.core.httpCacheBuster
 *
 * @description
 * Request interceptor to invalidate browser cache of templates.
 *
 * @requires $templateCache
 * @requires parameters
 */
function CacheBusterInterceptorFactory ($templateCache, parameters) {

  return {
    request: request,
  };

  function request (requestConfig) {
    if ('GET' === requestConfig.method) {
      var ext = requestConfig.url.substr(requestConfig.url.lastIndexOf('.') + 1);

      // requiring a template that is not aleady cached
      if ('html' === ext && !$templateCache.get(requestConfig.url)) {
        requestConfig.url += (requestConfig.url.indexOf('?') === -1 ? '?' : '&');
        requestConfig.url += 'v=' + parameters.version;
      }
    }

    return requestConfig;
  }

}

})(angular);
