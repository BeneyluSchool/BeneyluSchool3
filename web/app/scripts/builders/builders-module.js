(function (angular) {
'use strict'  ;

angular.module('bns.builders', [
  // config
  'bns.builders.config.states',
  // controllers
  'bns.builders.adminMessagesController',
  // directives
  'bns.builders.messageFeed',
  // services
  'bns.builders.resources',
]);

})(angular);
