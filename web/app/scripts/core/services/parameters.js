(function (angular) {
'use strict';

angular.module('bns.core.parameters', [])

  .provider('parameters', ParametersProvider)

;

function ParametersProvider () {

  // ugly way to get acces to parameters during config
  this.get = function (name) {
    /* global window */
    if (window.bns_parameters) {
      return window.bns_parameters[name];
    }

    return null;
  };

  // this is simply a proxy to the parameters object attached to the window
  this.$get = ['$window', function ($window) {
    return $window.bns_parameters;
  }];

}

}) (angular);
