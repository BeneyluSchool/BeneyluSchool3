(function (angular) {
'use strict'  ;

angular.module('bns.homework.back.weekControllers', [
  'angularMoment',
  'bns.homework.homeworks',
  'bns.homework.manager',
])

  .controller('HomeworkBackWeekActionbar', HomeworkBackWeekActionbarController)
  .controller('HomeworkBackWeekContent', HomeworkBackWeekContentController)
  .controller('HomeworkBackWeekSidebar', HomeworkBackWeekSidebarController)
  .factory('homeworkBackWeekState', HomeworkBackWeekStateFactory)

;

function HomeworkBackWeekActionbarController ($scope, homeworkManager, preferences, homeworkBackWeekState) {

  $scope.shared = homeworkBackWeekState;
  $scope.shared.dateForCreate = null;

  init();

  function init () {
    $scope.$watch('shared.date', function (date) {
      if (!date) {
        $scope.shared.dateForCreate = null;
        return;
      }

      $scope.shared.dateForCreate = homeworkManager.getNextCreationDate(date, preferences);
    });
  }

}

function HomeworkBackWeekContentController (_, $scope, $state, $stateParams, moment, dateUtils, Homeworks, homeworkBackWeekState, preferences) {

  if (!dateUtils.isMonday($stateParams.week)) {
    return $state.go('app.homework.back.week.content', { week: dateUtils.getCurrentMonday() });
  }

  var shared = $scope.shared = homeworkBackWeekState;
  var ctrl = this;
  ctrl.preferences = preferences;

  init();

  function init () {
    var monday = $stateParams.week;
    shared.date = moment.utc(monday);

    $scope.$watch('shared.filters', function () {
      var params = {};
      if (shared.filters.subjects.length) {
        params.subjects = shared.filters.subjects.join(',');
      }
      if (shared.filters.groups.length) {
        params.groups = shared.filters.groups.join(',');
      }
      if (shared.filters.days.length) {
        params.days = shared.filters.days.join(',');
      }
      loadHomeworks(monday, params);
    }, true);
  }

  function loadHomeworks (day, params) {
    ctrl.busy = true;

    return Homeworks.one('week').one(day).get(params)
      .then(function success (homeworks) {
        shared.homeworks = homeworks;
        shared.homeworks.grouped = _.groupBy(homeworks, 'due_date');
      })
      .catch(function error (response) {
        if (404 === response.status) {
          shared.homeworks = [];
        }
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

function HomeworkBackWeekSidebarController (_, $scope, $state, $mdSidenav, moment, navbar, Groups, Homeworks, preferences, homeworkBackWeekState) {

  $scope.shared = homeworkBackWeekState;

  var ctrl = this;
  ctrl.groups = [];               // available groups
  ctrl.days = [];                 // available days

  init();

  function init () {
    loadSubjects();

    $scope.$on('calendar.selection', function (event, date) {
      $mdSidenav('left').close();
      date = moment.utc(date).isoWeekday(1);
      $state.go('.', {week: date.format('YYYY-MM-DD')});
    });

    ctrl.days = _.map(preferences.days, function (day) {
      return {
        value: day,
        label: 'HOMEWORK.LABEL_DAY_'+day,
      };
    });

    return Groups.getList({right: 'HOMEWORK_ACCESS_BACK'})
      .then(function success (groups) {
        ctrl.groups = _.map(groups, function (group) {
          return {
            value: group.id,
            label: group.label
          };
        });
      })
    ;
  }

  function loadSubjects () {
    return navbar.getOrRefreshGroup().then(function (group) {
      return Homeworks.one('groups').one(''+group.id).all('subjects').getList()
        .then(function (subjects) {
          ctrl.subjects = _.map(subjects, function (subject) {
            return {
              value: subject.id,
              label: subject.name,
            };
          });
        })
      ;
    });
  }

}

function HomeworkBackWeekStateFactory () {

  return {
    filters: {
      subjects: [],
      groups: [],
      days: [],
    }
  };

}

})(angular);
