(function (angular) {
'use strict';

angular.module('bns.embed.embeds', [
  'restangular',
])

  .factory('Embeds', EmbedsFactory)

;

function EmbedsFactory (_, Restangular) {

  return Restangular.service('embeds');

}

})(angular);
