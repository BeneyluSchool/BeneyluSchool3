(function (angular) {
'use strict';

angular.module('bns.messaging.folders', [
  'restangular',
])

  .factory('MessagingFolders', MessagingFoldersFactory)

;

function MessagingFoldersFactory (Restangular) {

  return Restangular.service('folders', Restangular.one('messaging', ''));

}

})(angular);
