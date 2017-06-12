(function (angular) {
  'use strict';

  angular.module('bns.search.backGeneralWhitelistController', [
      'bns.search.search'
    ])

    .controller('SearchBackGeneralWhitelistContent', SearchBackGeneralWhitelistContentController)
    .controller('SearchBackGeneralWhitelistSidebar', SearchBackGeneralWhitelistSidebarController)

  ;

  function SearchBackGeneralWhitelistContentController (generalWhiteList) {

    var ctrl = this;

    init();

    function init() {
      ctrl.search = generalWhiteList;
    }
  }

  function SearchBackGeneralWhitelistSidebarController (Restangular, generalWhiteList, $q) {

    var ctrl = this;
    ctrl.search = generalWhiteList;

    ctrl.buildGeneralWhiteListSwitchManager = {
      getStatus: function () {
        return $q.when({
          status: !!parseInt(ctrl.search.white_list_use, 10)
        });
      },
      toggle: function (status) {
        return Restangular.all('search').all('general-white-list').all('toggle').post({toggle: status})
          .then(function( data) {
            ctrl.search.white_list_use = !!data.status;
              return {status: ctrl.search.white_list_use};
          });
      }
    };
  }


})(angular);
