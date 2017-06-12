(function (angular) {
'use strict'  ;

angular.module('bns.olympics.config.theme', [])

  .config(OlympicsThemeConfig)

;

function OlympicsThemeConfig ($mdThemingProvider) {

  $mdThemingProvider.theme('olympics')
    .accentPalette('amber', {
      'default': '600',
    })
  ;

}

})(angular);
