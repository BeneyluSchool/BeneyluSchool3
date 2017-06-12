(function (angular) {
'use strict';

angular.module('bns.core.httpAuthExceptionInterceptor', [
])

  .config(HttpAuthExceptionInterceptorConfig)

;

function HttpAuthExceptionInterceptorConfig ($httpProvider, $provide) {
  // register the interceptor as a service
  $provide.factory('httpAuthExceptionInterceptor', function($q, $window, Routing) {
    return {
      'responseError': function(rejection) {
        if (rejection.status === 401) {
          $window.location = Routing.generate('disconnect_user');
          return $q.reject(rejection);
        }
        // do something on error
        console.debug('interceptor responseError', rejection);
        return $q.reject(rejection);
      }
    };
  });

  $httpProvider.interceptors.push('httpAuthExceptionInterceptor');
}

}) (angular);
