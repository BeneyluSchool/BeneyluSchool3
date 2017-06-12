(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.main.tinymce
 */
angular.module('bns.main.highchart', [
  'bns.core.loader',
  'bns.core.parameters',
])

  .directive('bnsHighchart', BNSHighchartDirective)

;

/**
 * @ngdoc directive
 * @name bnsHighchart
 * @module bns.main.highchart
 *
 * @description
 * Wrapper for the highchart directive, that lazy load the highchart lib
 *
 * @requires $compile
 * @requires $window
 * @requires $locale
 * @requires $translate
 * @requires Loader
 * @requires parameters
 * @requires moment
 */
function BNSHighchartDirective ($compile, $window, $timeout, $locale, $translate, Loader, parameters, moment) {

  return {
    restrict: 'A',
    scope: true,
    link: postLink,
    terminal: true,
    priority: 1050,
  };

  function postLink (scope, element) {
    var base = parameters.app_base_path;
    var version = parameters.version || 'version';

    setupHighchart();

    function setupHighchart () {
      // no highchart lib present, delay conf and load it
      if (!$window.Highcharts) {
        var loader = new Loader();

        return loader.require([
          buildPathVersion('/highcharts/highcharts.js'),
          buildPathVersion('/highcharts/modules/exporting.src.js'),
          buildPathVersion('/highcharts/modules/offline-exporting.src.js'),
        ], function highchartLoad () {
          // wrap in timeout to avoid race conditions
          $timeout(function () {
            setupHighchart();
          });
          initTranslations();
        });
      }

      element.removeAttr('bns-highchart');
      element.attr('highchart', '');

      // compile to tell angular to handle highchart directive
      $timeout(function(){
        $compile(element)(scope);
      }, 0);
    }

    function initTranslations () {
        $translate(['STATISTIC.LOADING', 'STATISTIC.FROM', 'STATISTIC.TO', 'STATISTIC.PERIOD', 'STATISTIC.ZOOM']).then(function (translations) {

      var formats = $locale.NUMBER_FORMATS;

      var highchartTranslations = {
        loading: translations['STATISTIC.LOADING'],
        months: moment.months(),
        weekdays: moment.weekdays(),
        shortMonths: moment.monthsShort(),
        rangeSelectorFrom: translations['STATISTIC.FROM'],
        rangeSelectorTo: translations['STATISTIC.TO'],
        rangeSelectorZoom: translations['STATISTIC.PERIOD'],
        resetZoom: translations['STATISTIC.ZOOM'],
        resetZoomTitle: translations['STATISTIC.ZOOM'],
        thousandsSep: formats.GROUP_SEP,
        decimalPoint: formats.DECIMAL_SEP
      };

        $window.Highcharts.setOptions({
            lang: highchartTranslations
          });
        });
    }

    function buildPathVersion(path) {
      return base + '/bower_components/' + path + '?v=' + version;
    }
  }
}

})(angular);
