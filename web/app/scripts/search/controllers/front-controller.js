(function (angular) {
'use strict';

angular.module('bns.search.frontController', [
  'bns.search.google',
  'bns.search.search'
])

  .controller('SearchFront', SearchFrontController)

;

function SearchFrontController (GoogleSearch, Search,  toast, Routing) {


  // TODO: get additional search engines from API, display links in front

  var ctrl = this;
  ctrl.query = '';
  ctrl.doSearch = doSearch;
  ctrl.uaiUrl = Routing.generate('BNSAppSearchBundle_universalis');
  ctrl.medialandesUrl = Routing.generate('BNSAppMainBundle_medialandes_index');

  init();

  function init () {
    ctrl.busy = true;

    Search.one('white-list').one('url').get()
      .then(function success (search) {
        ctrl.search = search;
        ctrl.crefUrl = ctrl.search.white_list_url;
        ctrl.hasMediaLandes = ctrl.search.has_medialandes;
        ctrl.hasUai = ctrl.search.hasUai;
        ctrl.images = ctrl.search.images;

        GoogleSearch.load(ctrl.search.cse).then(createSearchControl, function(error) {
          console.error('cannot load google', error);
        });

      })
      .catch(function error (response) {
        toast.error('SEARCH.GET_WHITE_LIST_ERROR');
        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function createSearchControl (googleSearch) {
    ctrl.searchControl = googleSearch;
  }

  function doSearch () {
    if (!(ctrl.query && ctrl.searchControl)) {
      return;
    }

    angular.element('md-content').addClass('search-results');

    // TODO render only when needed
    ctrl.searchControl.cse.element.go('search-results');

    var element = ctrl.searchControl.cse.element.getElement('searchbox');
    if (element) {
      element.execute(ctrl.query);
      Search.post({label: ctrl.query});
    }
  }

}

})(angular);
