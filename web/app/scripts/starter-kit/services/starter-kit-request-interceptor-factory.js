(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.requestInterceptor
 */
angular.module('bns.starterKit.requestInterceptor', [
  'bns.core.assetize',
])

  .factory('StarterKitRequestInterceptor', StarterKitRequestInterceptorFactory)

;

/**
 * @ngdoc service
 * @name StarterKitRequestInterceptor
 * @module bns.starterKit.requestInterceptor
 *
 * @description
 * Factory for a request interceptor that decorates all requests with starter
 * kit data, if necessary.
 *
 * @requires Restangular
 * @requires assetizeFilter
 */
function StarterKitRequestInterceptorFactory (Restangular, assetizeFilter) {

  function StarterKitRequestInterceptor (kit) {
    this.kit = kit;
  }

  StarterKitRequestInterceptor.prototype.handle = function(element, operation, what, url, headers, params, httpConfig) {
    if (!(this.kit.enabled && this.kit.current)) {
      return;
    }

    // rough check to see if request is concerned by current starter kit: the
    // api domain is the current app
    var app = assetizeFilter(this.kit.app);
    var urlPattern = new RegExp('^' + Restangular.configuration.baseUrl + '/' + app);
    if (!urlPattern.test(url)) {
      return;
    }

    // simply add the current step as a parameter
    return {
      element: element,
      headers: headers,
      params: angular.merge(params, {
        step: this.kit.current.step,
      }),
      httpConfig: httpConfig
    };
  };

  return StarterKitRequestInterceptor;

}

})(angular);
