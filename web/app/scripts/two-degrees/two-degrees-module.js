(function (angular) {
'use strict';

angular.module('bns.twoDegrees', [
  'bns.main.downloadFolder',

  // config
  'bns.twoDegrees.config.states',
  'bns.twoDegrees.config.theme',

  // directives
  'bns.twoDegrees.challengeSolver',
  'bns.twoDegrees.counter',
  'bns.twoDegrees.panel',
  'bns.twoDegrees.thermometer',
]);

})(angular);
