'use strict';

angular.module('bns.statistic.front.sidebarController', [
  'ui.router',
  'bns.statistic.state',
  //'checklist-model'
  //'bns.statistic.filters',
])

  .controller('StatisticFrontSidebarController', function (_, $rootScope, $scope, $state, $timeout, $window, statistics, groups, statisticRestangular, statisticState) {
    var ctrl = this;

    ctrl.filters = statisticState.filters;
    ctrl.statistics = statistics;
    ctrl.groups = groups;
    ctrl.busy = statisticState.busy;
    //_.map(groups, function(group){
    //  return {
    //    value: group.id,
    //    label: group.label,
    //  };
    //});
    ctrl.periodPreSelect = statisticState.getPeriodPreSelects;
    ctrl.preSelectDate = preSelectDate;
    ctrl.iconFunc = iconFunc;

    init();
    function init() {

      ctrl.preSelect = 'STATISTIC.PERIOD_LAST_30_DAY';
      preSelectDate();
      var elements = _.findWhere(ctrl.statistics, {name: $state.params.statistic});
      if ($state.params.statistic && undefined !== elements) {
        ctrl.filters.statistic =  elements;
      }
      if (!ctrl.filters.statistic) {
        ctrl.filters.statistic = _.first(statistics);
      }

      //var first =  _.first(ctrl.groups);
      //if( first ){
      //  ctrl.filters.groups = [
      //    first.value
      //  ];
      //} else {
        ctrl.filters.groups = [];
      //}
    }

    function preSelectDate() {
      if (angular.isDefined(ctrl.periodPreSelect[ctrl.preSelect])) {
        ctrl.filters.start = ctrl.periodPreSelect[ctrl.preSelect].start;
        ctrl.filters.end = ctrl.periodPreSelect[ctrl.preSelect].end;
      }
    }

    function iconFunc(object) {
      return object;
    }

    $scope.$watch('ctrl.filters.statistic', function (value) {
      if (value && angular.isDefined(value.name)) {
        $state.go('app.statistic.front.base.page', {'statistic': value.name});
      }
    });

    $scope.$watch('ctrl.busy.state', function(newVal, oldVal){
      if (newVal !== oldVal && !newVal) {
        $timeout(function(){
          // fix wrong size of graph / table on show
          /* globals Event: false */
          $window.dispatchEvent(new Event('resize'));
        }, 0);
      }
    });
  });
