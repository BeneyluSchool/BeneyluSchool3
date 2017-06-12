'use strict';

angular.module('bns.workshop.document.contributors2', [
  'bns.core.collectionMap',
  'bns.workshop.document.state',
])

  .factory('workshopDocumentContributors', function (CollectionMap, WorkshopDocumentState) {
    var srvc = {
      lockedUsers: new CollectionMap([], '#self'),
      lockedGroups: new CollectionMap([], '#self'),
      enable: enable,
      disable: disable,
    };

    return srvc;

    function enable () {
      srvc.lockedUsers.addc(WorkshopDocumentState.document._embedded.contributor_user_ids);
      srvc.lockedGroups.addc(WorkshopDocumentState.document._embedded.contributor_group_ids);

      console.log(srvc.lockedUsers);
    }

    function disable () {

    }
  })

;
