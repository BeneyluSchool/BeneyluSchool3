(function (angular) {
'use strict'  ;

angular.module('bns.minisite', [
  'bns.minisite.config.states',
  'bns.minisite.widgets',
  'bns.minisite.cityNews',
  'bns.minisite.scroll',

  'bns.minisite.back.cityNewsController', // instantiated by twig template
]);

})(angular);
