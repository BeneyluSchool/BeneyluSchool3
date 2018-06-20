(function (angular) {
'use strict';

var APP_NAME = 'beneyluSchoolAuthApp';

angular.module(APP_NAME, [
  // vendor modules
  'ngCookies',
  'ngMessages',
  'ngSanitize',

  // dummy translation, without conf
  'bns.core.translationInitLight',
  // 'pascalprecht.translate',

  // standalone UI components
  'bns.material',
  'bns.components.button',
  'bns.components.dialog',
  'bns.components.icon',
  'bns.components.input',
  'bns.components.passwordToggle',
  'bns.components.tabs',
  'bns.components.toast',

  // core components
  'bns.core.exposeComponentsRoot',
  'bns.core.input',
  'bns.core.libraries',
  'bns.core.nofTheme',
  'bns.core.viewsLight',

  // app-wide stuff
  'bns.main.loginController',
  'bns.main.autoLoginBox',
])

;

}) (angular);
