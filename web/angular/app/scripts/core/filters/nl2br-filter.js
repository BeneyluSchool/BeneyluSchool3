'use strict';

angular.module('bns.core.nl2br', [])

  .filter('nl2br', function () {
    return function (str) {
      return str ? str.replace(/\n/g, '<br>') : '';
    };
  });
