(function (angular) {
  'use strict'  ;

  angular.module('bns.lunch.front.showControllers', [
    'ui.router',
    'angularMoment',
    'bns.core.dateUtils',
    'bns.lunch.lunchWeek',
    'bns.lunch.lunchDisplay',
    ])

  .controller('LunchWeekFrontShowContentController', LunchWeekFrontShowContentController)
  .controller('LunchWeekFrontShowActionbarController', LunchWeekFrontShowActionbarController)
  .controller('LunchWeekFrontShowSidebarController', LunchWeekFrontShowSidebarController)
  .factory('lunchWeekShowState', LunchWeekShowStateFactory)
  .factory('lunchDisplay', LunchDisplayFactory)
  .filter('lunchWeekDisplayLabel', lunchWeekDisplayLabel)
  ;

  function LunchWeekFrontShowContentController ($scope, $state, $stateParams, moment, dateUtils, LunchWeek, LunchWeekEditor, lunchWeekShowState, lunchDisplay, Restangular) {

    if (setValidFirstDay()) {
      return; // has a state redirect, do nothing and wait for it
    }

    var ctrl = this;
    var shared = $scope.shared = lunchWeekShowState;
    $scope.display = lunchDisplay;
    ctrl.editor = LunchWeekEditor;
    ctrl.isSectionEmpty = isSectionEmpty;

    init();
    $scope.month = Restangular.one('lunch').one('weeks').one($scope.shared.date.format('YYYY-MM-DD')).all('monthly').getList().$object;


    $scope.day_index = 0;
    $scope.next = function() {
      if ($scope.day_index  === 0) {
        return $state.go('app.lunch.front.views', { week: $scope.shared.date.clone().add(1, 'days').format('YYYY-MM-DD') });
      }
      else {
        $scope.day_index ++;
      }
    };
    $scope.prev = function() {
      if ($scope.day_index === 0) {
        return $state.go('app.lunch.front.views', { week: $scope.shared.date.clone().subtract(1, 'days').format('YYYY-MM-DD') });
      }
      else {
        $scope.day_index --;
      }
    };

    $scope.nextMonth = function() {
      var current = $scope.shared.date.clone();
      var result = current.add(1, 'months').startOf('month');
        result = result.format('YYYY-MM-DD');
        return $state.go('app.lunch.front.views', { week: result });
    };

    $scope.prevMonth = function() {
      var current = $scope.shared.date.clone();
      var result = current.subtract(1, 'months').startOf('month');
        result = result.format('YYYY-MM-DD');
        return $state.go('app.lunch.front.views', { week: result });
    };

    $scope.$watch('display.data', function (newValue) {
      if ( newValue === 'week') {
        setValidFirstDay();
      }
    });

    function init () {
      var monday = $stateParams.week;
      ctrl.busy = true;
      shared.date = moment.utc(monday);

      LunchWeek.one(monday).get()
      .then(function (lunchWeek) {
        shared.lunchWeek = lunchWeek;
      })
      .catch(function (response) {
        if (404 === response.status) {
          shared.lunchWeek = {};
        }
      })
      .finally(function end () {
        ctrl.busy = false;
      })
      ;

    }

    function setValidFirstDay () {
      if (lunchDisplay.data === 'week' || !$stateParams.week) {
        if (!dateUtils.isMonday($stateParams.week)) {
          return $state.go('app.lunch.front.views', { week: dateUtils.getCurrentMonday() });
        }
      }
    }

    function isSectionEmpty (section) {
      if (!(shared.lunchWeek && shared.lunchWeek._embedded && shared.lunchWeek._embedded.days)) {
        return;
      }

      for (var i = 0; i < shared.lunchWeek._embedded.days.length; i++) {
        if (shared.lunchWeek._embedded.days[i][section]) {
          return false;
        }
      }

      return true;
    }

  }

  function LunchWeekFrontShowActionbarController ($scope, $state, $stateParams, lunchWeekShowState, lunchDisplay, $timeout, $window) {

    $scope.shared = lunchWeekShowState;
    var ctrl = this;
    ctrl.monday = $stateParams.week;

    $scope.menuPrint = function(index) {
      if(index === 0) {
        lunchDisplay.data = 'week';
        $timeout(function() {
            $window.print();
          }, 500);
      }
      else {
        lunchDisplay.data = 'month';
        $timeout(function() {
            $window.print();
          }, 500);
      }
    };

  }

  function LunchWeekFrontShowSidebarController ($scope, $state, $mdSidenav, moment, lunchWeekShowState, lunchDisplay) {

    $scope.shared = lunchWeekShowState;
    $scope.display = lunchDisplay;


    $scope.$on('calendar.selection', function (event, date) {
      $mdSidenav('left').close();
      date = moment.utc(date).isoWeekday(1);

      if ($scope.display.data === 'day') {
        $state.go('app.lunch.front.views', { week: date._i});
      } else {
        $state.go('.', {week: date.format('YYYY-MM-DD')});
      }
    });

  }

  function LunchWeekShowStateFactory () {

    return {};

  }

  function LunchDisplayFactory () {

    return {
      data: 'week'
    };

  }

  function lunchWeekDisplayLabel (moment){
    return function (value, format) {
      return moment(value, format);
    };
  }

})(angular);
