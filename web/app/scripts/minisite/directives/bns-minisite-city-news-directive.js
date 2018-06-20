(function (angular) {
'use strict';

angular.module('bns.minisite.cityNews', [])

  .directive('bnsMinisiteCityNews', BNSMinisiteCityNewsDirective)
  .controller('BNSMinisiteCityNews', BNSMinisiteCityNewsController)

;

function BNSMinisiteCityNewsDirective () {

  return {
    scope: {
      groupId: '@',
    },
    controller: 'BNSMinisiteCityNews',
    controllerAs: 'ctrl',
    bindToController: true,
    templateUrl: 'views/minisite/directives/bns-minisite-city-news.html',
  };

}

function BNSMinisiteCityNewsController (Restangular) {

  var ctrl = this;

  init();

  function init () {
    Restangular.one('groups', ctrl.groupId).all('minisite').one('city-news').get()
      .then(function success (data) {
        ctrl.data = data;
      })
    ;
  }

}

})(angular);
