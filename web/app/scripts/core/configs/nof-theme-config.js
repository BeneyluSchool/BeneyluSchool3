(function (angular) {
'use strict';

angular.module('bns.core.nofTheme', [])

  .config(NofThemeConfig)

;

function NofThemeConfig ($mdThemingProvider) {

  var primaryColor = '#002642';
  var dangerColor = '#ff3030';

  // define our custom palettes
  $mdThemingProvider.definePalette('nof-indigo', $mdThemingProvider.extendPalette('indigo', {
    '900': primaryColor,
    // contrastDefaultColor: 'light',
  }));
  $mdThemingProvider.definePalette('nof-red', $mdThemingProvider.extendPalette('red', {
    '500': dangerColor,
    // contrastDefaultColor: 'light',
  }));
  $mdThemingProvider.definePalette('nof-grey', $mdThemingProvider.extendPalette('grey', {
    '50': '#ffffff', // override gray-50 by pure white
  }));

  // register our custom theme
  $mdThemingProvider.theme('nof')
    .primaryPalette('nof-indigo', {
      'default': '900',
    })
    .warnPalette('nof-red')
    .backgroundPalette('nof-grey')
    // .foregroundPalette['1'] = primaryColor
  ;

}

})(angular);
