(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.portal.minisiteWidget
   */
  angular.module('bns.portal.minisiteWidget', [
    'bns.core.collectionMap'
  ])

    .directive('bnsPortalMinisiteWidget', BNSPortalMinisiteWidgetDirective)
    .controller('BNSPortalMinisiteWidget', BNSPortalMinisiteWidgetController)
  ;

  /**
   * @ngdoc directive
   * @name bnsPortalMinisiteWidget
   * @module bns.portal.minisiteWidget
   *
   * @description
   * Manage list of minisites
   */
  function BNSPortalMinisiteWidgetDirective () {

    return {
      templateUrl: 'views/portal/directives/bns-portal-minisite-widget.html',
      controller: 'BNSPortalMinisiteWidget',
      controllerAs: 'widget',
      bindToController: true,
      scope:  {
        'widgetId': '@',
        'groupId': '@'
      }
    };

  }

  function BNSPortalMinisiteWidgetController ($rootScope, $scope, _, Restangular) {

    var widget = this;
    widget.busy = true;
    widget.all = null;
    widget.listIds = [];
    widget.lists = [];
    widget.choices = [];

    widget.toggle = function (item, list) {
      var idx = list.indexOf(item);
      if (idx > -1) {
        list.splice(idx, 1);
      } else {
        list.push(item);
      }
    };
    widget.expand = function(){
      widget.expanded =!widget.expanded;
    };

    widget.exists = function (item, list) {
      return list.indexOf(item) > -1;
    };

    widget.toggleAll = function() {
      widget.all = !widget.all;
    };

    init();

    $scope.$watchCollection('widget.lists', function(){
      widget.choices = _.map(widget.lists, function(item) {
        return {
          label: item.name,
          value: parseInt(item.id, 10),
          icon: {
            unique_name: 'GROUP',
            group_type: 'CITY',
          }
        };
      });
    });

    function load () {
      Restangular.one('user-directory').one('groups').one(widget.groupId).one('distribution-lists').get({'type' : 'STRUCT'})
        .then(function success (data) {
          widget.lists = data;
        });
    }

    function init () {
      widget.sortableConfig = {
        handle: '.drag-handle'
      };

      load();

      var unlistenListsSelect = $rootScope.$on('userDirectory.selection', function () {
        load();
      });

      var unlistenListsCancel = $rootScope.$on('userDirectory.cancel', function () {
        load();
      });

      $scope.$on('$destroy', function () {
        unlistenListsSelect();
        unlistenListsCancel();
      });

      Restangular.one('portal').one(widget.groupId).one('widget').one(widget.widgetId).get()
        .then(function success (data) {
          widget.content = data;
          if(data.datas) {
            if (data.datas.lists) {
              widget.listIds = _.map(data.datas.lists, function(item) {
                return parseInt(item, 10);
              });

            }
            widget.all = data.datas.all || false;
          }
        })
        .catch(function error (response) {
          console.error(response);
        })
        .finally(function end () {
          widget.busy = false;
        });

    }
  }

})(angular);
