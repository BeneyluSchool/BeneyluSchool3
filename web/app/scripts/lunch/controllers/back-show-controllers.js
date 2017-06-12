(function (angular) {
'use strict'  ;

angular.module('bns.lunch.back.showControllers', [
  'ui.router',
  'angularMoment',
  'bns.lunch.lunchWeek',
])

  .controller('LunchWeekBackShowContentController', LunchWeekBackShowContentController)
  .controller('LunchWeekBackShowActionbarController', LunchWeekBackShowActionbarController)
  .controller('LunchWeekBackShowSidebarController', LunchWeekBackShowSidebarController)
  .factory('lunchWeekShowState', LunchWeekShowStateFactory)

;

function LunchWeekBackShowContentController ($scope, $state, $stateParams, moment, dateUtils, LunchWeek, LunchWeekEditor, lunchWeekShowState) {

  if (!dateUtils.isMonday($stateParams.week)) {
    return $state.go('app.lunch.back.week', { week: dateUtils.getCurrentMonday() });
  }

  var ctrl = this;
  var shared = $scope.shared = lunchWeekShowState;
  ctrl.editor = LunchWeekEditor;
  ctrl.isSectionEmpty = isSectionEmpty;

  init();

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

function LunchWeekBackShowActionbarController ($scope, $state, $stateParams, lunchWeekShowState) {

  $scope.shared = lunchWeekShowState;

  var ctrl = this;
  ctrl.monday = $stateParams.week;
  ctrl.edit = edit;

  function edit () {
    if (ctrl.monday) {
      return $state.go('app.lunch.back.week.edit', { week: ctrl.monday });
    }
  }

}

function LunchWeekBackShowSidebarController ($scope, $state, $mdSidenav, moment, lunchWeekShowState) {

  $scope.shared = lunchWeekShowState;

  $scope.$on('calendar.selection', function (event, date) {
    $mdSidenav('left').close();
    date = moment.utc(date).isoWeekday(1);
    $state.go('.', {week: date.format('YYYY-MM-DD')});
  });

}

function LunchWeekShowStateFactory () {

  return {};

}

})(angular);
