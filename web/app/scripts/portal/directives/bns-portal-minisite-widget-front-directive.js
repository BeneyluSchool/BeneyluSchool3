(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.portal.minisiteWidgetFront
   */
  angular.module('bns.portal.minisiteWidgetFront', [])

    .directive('bnsPortalMinisiteWidgetFront', BNSPortalMinisiteWidgetFrontDirective)
    .controller('BNSPortalMinisiteWidgetFront', BNSPortalMinisiteWidgetFrontController)
  ;

  /**
   * @ngdoc directive
   * @name bnsPortalMinisiteWidgetFront
   * @module bns.portal.minisiteWidgetFront
   *
   * @description
   * Manage list of minisites
   */
  function BNSPortalMinisiteWidgetFrontDirective () {

    return {
      templateUrl: 'views/portal/directives/bns-portal-minisite-widget-front.html',
      controller: 'BNSPortalMinisiteWidgetFront',
      controllerAs: 'widget',
      bindToController: true,
      scope:  {
        'widgetId': '@',
        'groupId': '@',
        'portalZone': '@'
      }
    };

  }

  function BNSPortalMinisiteWidgetFrontController (Restangular, $scope) {
    var widget = this;
    widget.busy = true;
    var limit = 10;

    init();

    function init () {
      Restangular.one('portal').one(widget.groupId).one('minisites').one(widget.widgetId).get({limit: limit})
        .then(function success (data) {
          widget.content = data;
        })
        .catch(function error (response) {
          console.error(response);
        })
        .finally(function end () {
          widget.busy = false;
        });
    }

    $scope.prev = function(page) {
      widget.busy = true;
      page = page - 1;
      Restangular.one('portal').one(widget.groupId).one('minisites').one(widget.widgetId).get({page: page, limit: limit})
        .then(function success (data) {
          widget.content = data;
        })
        .catch(function error (response) {
          console.error(response);
        })
        .finally(function end () {
          widget.busy = false;
       });
    };
    $scope.next = function(page) {
      widget.busy = true;
      page = page + 1;
      Restangular.one('portal').one(widget.groupId).one('minisites').one(widget.widgetId).get({page: page, limit: limit})
        .then(function success (data) {
          widget.content = data;
        })
        .catch(function error (response) {
          console.error(response);
        })
        .finally(function end () {
          widget.busy = false;
       });
    };
  }

})(angular);
