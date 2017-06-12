'use strict';

angular.module('bns.statistic.front.actionbarController', [])

  .controller('StatisticFrontActionbarController', function (statisticState) {

    var ctrl = this;

    ctrl.state = statisticState;
    ctrl.exportChartPng = exportChartPng;
    ctrl.exportChartPdf = exportChartPdf;

    init();

    function init() {
    }

    function exportChartPng() {
      if (ctrl.state.chartConfig) {
        var chart = ctrl.state.chartConfig.getHighcharts();
        if (chart) {
          chart.exportChartLocal({
            filename: ctrl.state.title,
            url: 'https://export.highcharts.com',
          });
        }
      }
    }

    function exportChartPdf() {
      if (ctrl.state.chartConfig) {
        var chart = ctrl.state.chartConfig.getHighcharts();
        if (chart) {
          chart.exportChart({
            filename: ctrl.state.title,
            type: 'application/pdf',
            url: 'https://export.highcharts.com',
          });
        }
      }
    }

  });
