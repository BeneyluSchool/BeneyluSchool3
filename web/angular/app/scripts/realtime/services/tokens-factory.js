'use strict';

angular.module('bns.realtime.tokens', [])

  .factory('Tokens', function (Restangular) {
    return Restangular.service('tokens');
  })

;
