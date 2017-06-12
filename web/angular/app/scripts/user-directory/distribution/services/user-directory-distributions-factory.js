(function (angular) {
'use strict';

angular.module('bns.userDirectory.distribution.userDirectoryDistributions', [
  'bns.userDirectory.groups',
])

  .factory('userDirectoryDistributions', UserDirectoryDistributionsFactory)

;

function UserDirectoryDistributionsFactory (UserDirectoryRestangular, userDirectoryGroups) {

  return {
    getList: getList,
    get: get,
    remove: remove,
    lookup: lookup,
  };

  function getList (groupId) {
    return userDirectoryGroups.get(groupId).then(function (group) {
      return group.all('distribution-lists').getList();
    });
  }

  function get (id) {
    return UserDirectoryRestangular.all('distribution-lists').get(id);
  }

  function remove (ids) {
    return UserDirectoryRestangular.all('distribution-lists').remove({ids: ids});
  }

  function lookup (ids) {
    return UserDirectoryRestangular.all('distribution-lists').all('lookup').getList({
      ids: ids.join(','),
    });
  }

}

})(angular);
