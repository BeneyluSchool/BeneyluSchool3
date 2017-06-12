(function (angular) {
  'use strict';

  angular.module('bns.homework.front.dayControllers', [
    'bns.homework.manager',
    ])

  .controller('HomeworkFrontDayContent', HomeworkFrontDayContentController)
  .controller('HomeworkFrontDaySidebar', HomeworkFrontDaySidebarController)
  .factory('homeworkFrontDayState', HomeworkFrontDayStateFactory)
  .filter('strLimit', ['$filter', function($filter) {
    return function (input, limit) {
      if (!input) {
        return;
      }
      if (input.length <= limit) {
        return input;
      }

      return $filter('limitTo')(input, limit) + '...';
    };
  }])

  ;

  function HomeworkFrontDayContentController (
    $scope, $state, $stateParams, moment, toast, Users, Homeworks, homeworkManager,
    preferences, homeworkFrontDayState
    ) {

    if (!$stateParams.day) {
      return $state.go('app.homework.front.day', {
        day: homeworkManager.getNextVisibleDay(preferences).format('YYYY-MM-DD'),
      });
    }

    var shared = $scope.shared = homeworkFrontDayState;
    shared.day = moment($stateParams.day);

    var ctrl = this;
    ctrl.canMarkDone = canMarkDone;
    ctrl.markDone = markDone;
    ctrl.countDone = countDone;
    ctrl.preferences = preferences;

    ctrl.max = 250;

    init();

    function init () {
      ctrl.busy = true;

      if (!angular.isDefined(shared.canCreate)) {
        Users.hasCurrentRight('HOMEWORK_ACCESS_BACK').then(function (hasRight) {
          shared.canCreate = hasRight;
        });
      }

      return Homeworks.one('day').one($stateParams.day).getList()
      .then(function success (homeworks) {
        ctrl.homeworks = homeworks;
      })
      .catch(function error (response) {
        toast.error('HOMEWORK.GET_HOMEWORKS_ERROR');
        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
      ;
    }

    function canMarkDone (homeworkDue) {
      return 'undefined' !== typeof homeworkDue.done;
    }

    function markDone (homeworkDue) {
      return Homeworks.one('occurrences').one(''+homeworkDue.id).one('done').post()
      .then(function success () {
        toast.success('HOMEWORK.FLASH_MARK_DONE_SUCCESS');
        homeworkDue.done = true;
      })
      .catch(function error (response) {
        toast.error('HOMEWORK.FLASH_MARK_DONE_ERROR');
        throw response;
      })
      ;
    }

    function countDone () {
      var total = 0;
      angular.forEach(ctrl.homeworks, function (homeworkDue) {
        if (homeworkDue.done) {
          total++;
        }
      });

      return total;
    }

  }

function HomeworkFrontDaySidebarController (
  $scope, $state, $mdSidenav, moment, homeworkFrontDayState, $stateParams,
  preferences
) {

  $scope.shared = homeworkFrontDayState;
  var ctrl = this;

  ctrl.dayText = {};
  ctrl.preferences = preferences;

  init();

  function init () {
    $scope.$on('calendar.selection', function (event, date) {
      $mdSidenav('left').close();
      $state.go('.', {day: date.format('YYYY-MM-DD')});
    });
  }

}

function HomeworkFrontDayStateFactory () {

  return {};

}

})(angular);
