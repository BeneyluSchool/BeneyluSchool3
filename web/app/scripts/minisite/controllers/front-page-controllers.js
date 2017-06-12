(function (angular) {
  'use strict'  ;

  angular.module('bns.minisite.front.pageControllers', [
    'ui.router',
    'angularMoment',
  ])

  .controller('MinisiteFrontPageContentController', MinisiteFrontPageContentController)
;

  function MinisiteFrontPageContentController ($scope, $stateParams, Restangular, toast) {
    var ctrl = this;
    var limit = 5;
    var slug = $stateParams.slug;
    var page_slug = $stateParams.page_slug;

    init();

    $scope.next = function(page) {
      ctrl.busy = true;
      angular.element('body').scrollTop(0);
      page = page + 1;
      Restangular.one('minisite').one(slug).one('pages').one(page_slug).get({page: page, limit: limit})
        .then(function success (page) {
               ctrl.page = page;
           })
        .catch(function error (response) {
                toast.error('MINISITE.FLASH_GET_MESSAGE_ERROR');
                console.error(response);
              })
        .finally(function end () {
                ctrl.busy = false;
            });
     };

     $scope.prev = function(page) {
      ctrl.busy = true;
      angular.element('body').scrollTop(0);
      page = page - 1;
      Restangular.one('minisite').one(slug).one('pages').one(page_slug).get({page: page, limit: limit})
        .then(function success (page) {
               ctrl.page = page;
           })
        .catch(function error (response) {
                toast.error('MINISITE.FLASH_GET_MESSAGE_ERROR');
                console.error(response);
              })
        .finally(function end () {
                ctrl.busy = false;
            });
    };

    function addViews (page) {
      var promise;
      promise = page.patch();

      return promise
        .then(function success (response) {
          return response;
        })
        .catch(function error (response) {
          console.error(response);
        })
      ;
    }

    function init () {
      ctrl.busy = true;
      Restangular.one('minisite').one(slug).one('pages').one(page_slug).get({limit: limit})
      .then(function success (page) {
             ctrl.page = page;
             $scope.root.title = page.page.title;
             addViews(page);
         })
      .catch(function error (response) {
              toast.error('MINISITE.FLASH_GET_MESSAGE_ERROR');
              console.error(response);
            })
      .finally(function end () {
              ctrl.busy = false;
            });
    }

  }

})(angular);
