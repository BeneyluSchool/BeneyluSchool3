'use strict';

angular.module('bns.statistic.state', [])

/**
 * @ngdoc service
 * @name bns.statistic.state.userDirectoryState
 * @kind function
 *
 * @description
 *
 *
 * @returns {Object} The statisticState service
 */
  .factory('statisticState', function (moment, statisticRestangular) {
    var statistics;
    var groups;
    var busy = {state: 0};

    var periodPreSelects = {
      'STATISTIC.PERIOD_LAST_30_DAY': {
        start: moment().subtract(1, 'months').toDate(),
        end: moment().toDate(),
        order: 1,
      },
      'STATISTIC.PERIOD_LAST_MONTH': {
        start: moment().subtract(1, 'month').startOf('month').toDate(),
        end: moment().subtract(1, 'month').endOf('month').toDate(),
        order: 2,
      },
      'STATISTIC.PERIOD_CURRENT_WEEK': {
        start: moment().startOf('week').toDate(),
        end: moment().toDate(),
        order: 3,
      },
      'STATISTIC.PERIOD_CURRENT_SCHOOL_YEAR': {
        start: moment().month('september').subtract(moment().month() < 8 ? 1:0, 'year').startOf('month').toDate(),
        end: moment().toDate(),
        order: 4,
      }
    };

    return {
      filters: {
        statistic: null,
        start: null,
        end: null,
        groups: null,
        graph: null,
      },
      title: null,
      getStatistics: getStatistics,
      getGroups: getGroups,
      getPeriodPreSelects: periodPreSelects,
      busy: busy
    };

    function getStatistics() {
      if (!statistics) {
        busy.state++;
        statistics = statisticRestangular.all('statistics').getList();
        statistics.finally(function(){
          busy.state--;
        });
      }

      return statistics;
    }

    function getGroups() {
      if (!groups) {
        busy.state++;
        groups = statisticRestangular.all('filters').all('groups').getList();
        groups.finally(function(){
          busy.state--;
        });
      }

      return groups;
    }
  })

;
