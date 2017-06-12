'use strict';

angular.module('bns.statistic.front.contentController', [
  'ui.router',
  'highcharts-ng',

  // dependency lazy loaded
  //'ui.grid',
  //'ui.grid.grouping',
  //'ui.grid.selection',
  //'ui.grid.exporter',
  //'ui.grid.resizeColumns',
])

  .controller('StatisticFrontContentController', function ($scope, $timeout, statisticState, statisticRestangular, i18nService, $translate) {
    var ctrl = this;

    ctrl.state = statisticState;
    ctrl.filters = statisticState.filters;
    ctrl.gridConfig = false;
    ctrl.activationStats = false;
    ctrl.title = '';
    ctrl.graphInitilized = false;

    init();

    function init() {
      i18nService.setCurrentLang($translate.preferredLanguage());
    }

    function loadGraph() {
      ctrl.graphInitilized = false;
      if (ctrl.filters.statistic && ctrl.filters.statistic.graphs) {

        var form = {
          start: ctrl.filters.start,
          end: ctrl.filters.end,
          groups: ctrl.filters.groups,
        };
        ctrl.state.busy.state++;
        statisticRestangular.one(ctrl.filters.statistic.name).one('graphs').post(ctrl.filters.statistic.graphs[0].name, form)
          .then(function(chartConfig){

            $timeout(function(){
              if (ctrl.state.chartObject) {
                // reset color and symbol before chart redraw;
                ctrl.state.chartObject.colorCounter = 0;
                ctrl.state.chartObject.symbolCounter = 0;
              }

              if (ctrl.state.chartConfig) {
                // clean config and keep object reference to prevent bug with scope loosing reference
                for (var member in ctrl.state.chartConfig) {
                  delete ctrl.state.chartConfig[member];
                }
              } else {
                ctrl.state.chartConfig = {};
              }

              ctrl.state.chartConfig = angular.merge(ctrl.state.chartConfig, {
                options: {
                  navigation: {
                    buttonOptions: {
                      enabled: false
                    }
                  }
                },
                func: function(chart) {
                  ctrl.state.chartObject = chart;
                },
              }, chartConfig.config);
              ctrl.title = chartConfig.title;
              ctrl.state.title = chartConfig.title;
              ctrl.graphInitilized = true;
            },0);

          })
          .finally(function(){
            ctrl.state.busy.state--;
          });
      }
    }

    function loadTable() {
      if (ctrl.filters.statistic) {

        var form = {
          start: ctrl.filters.start,
          end: ctrl.filters.end,
          groups: ctrl.filters.groups,
        };

        ctrl.state.busy.state++;
        statisticRestangular.one(ctrl.filters.statistic.name).post('tables', form)
          .then(function (gridConfig) {

            ctrl.gridConfig =  gridConfig;
          })
          .finally(function(){
            ctrl.state.busy.state--;
          });
      }
    }

    function loadActivationStats() {
      ctrl.activationStats = false;
      if (ctrl.filters.statistic && 'CLASSROOM_SCHOOL_ACTIVATION' === ctrl.filters.statistic.name) {
        var form = {
          start: ctrl.filters.start,
          end: ctrl.filters.end,
          groups: ctrl.filters.groups,
        };

        ctrl.state.busy.state++;
        statisticRestangular.one(ctrl.filters.statistic.name).post('globals', form)
          .then(function (activationStats) {

            ctrl.activationStats =  activationStats;
          })
          .finally(function(){
            ctrl.state.busy.state--;
          })
        ;
      }
    }

    $scope.$watch('ctrl.filters', function(){
      loadGraph();
      loadTable();
      loadActivationStats();

    }, true);

  })
;
