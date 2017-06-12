(function (angular) {
'use strict';

angular.module('bns.search.backLogController', [
  'bns.search.search'
])

  .controller('SearchBackLog', SearchBackLogController)

;

function SearchBackLogController (Search, toast) {

    var ctrl = this;


    init();

    function init () {
      ctrl.busy = true;

      Search.one('logs').get()
        .then(function success (search) {
          ctrl.search = search;
        })
        .catch(function error (response) {
          toast.error('SEARCH.GET_LOG_ERROR');
          throw response;
        })
        .finally(function end () {
          ctrl.busy = false;
        })
      ;
    }





}
})(angular);
