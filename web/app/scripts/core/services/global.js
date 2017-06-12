(function (angular) {
'use strict';

angular.module('bns.core.global', [])

  .provider('global', GlobalProvider)

;

function GlobalProvider () {

  /* global window */
  this.get = getter(window);

  this.$get = ['$window', function ($window) {
    return getter($window);
  }];

  function getter (obj) {
    return function (prop) {
      return obj[prop] || obj['bns_' + prop];
    };
  }

}

}) (angular);
