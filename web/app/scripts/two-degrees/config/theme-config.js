(function (angular) {
'use strict'  ;

angular.module('bns.twoDegrees.config.theme', [])

  .config(TwoDegreesThemeConfig)

;

function TwoDegreesThemeConfig ($mdThemingProvider) {


  // $mdThemingProvider.theme('default')
  //   .primaryPalette('light-blue', {
  //     'default': '600',
  //   })
  //   .accentPalette('bns-light-green', {
  //     'default': '500',
  //     'hue-1': '300',
  //     'hue-2': '800',
  //     'hue-3': 'A200',
  //   })
  // ;

  $mdThemingProvider.definePalette('bns-amber', $mdThemingProvider.extendPalette('amber', {
    contrastDefaultColor: 'light',
    contrastLightColors: ['500', '600', '700', '800', '900'],
  }));

  $mdThemingProvider.theme('two-degrees')
    .accentPalette('bns-amber', {
      'default': '600',
    })
  ;

}

})(angular);
