(function (angular) {
'use strict';

angular.module('bns.homework.back.createControllers', [
  'bns.main.navbar',
  'bns.user.groups',
  'bns.homework.manager',
  'bns.homework.homeworks',
])

  .controller('HomeworkBackCreateActionbar', HomeworkBackCreateActionbarController)
  .controller('HomeworkBackCreateContent', HomeworkBackCreateContentController)
  .controller('HomeworkBackCreateSidebar', HomeworkBackCreateSidebarController)
  .factory('homeworkBackCreateState', HomeworkBackCreateStateFactory)

;

function HomeworkBackCreateActionbarController ($scope, $state, previousState, homeworkBackCreateState) {

  $scope.shared = homeworkBackCreateState;

  var ctrl = this;
  ctrl.back = back;

  function back () {
    if (previousState.name) {
      $state.go(previousState.name, previousState.params);
    } else {
      $state.go('app.homework.back.week');
    }
  }

}

function HomeworkBackCreateContentController (_, moment, $scope, $state, $stateParams, toast, navbar, Groups, homeworkManager, Homeworks, preferences, homeworkBackCreateState) {

  var shared = $scope.shared = homeworkBackCreateState;
  shared.success = false;

  var ctrl = this;
  ctrl.add = add;
  ctrl.remove = remove;
  ctrl.submit = submit;
  ctrl.recurrences = {
    EVERY_WEEK: 'HOMEWORK.CHOICE_EVERY_WEEK',
    EVERY_TWO_WEEKS: 'HOMEWORK.CHOICE_EVERY_TWO_WEEK',
    EVERY_MONTH: 'HOMEWORK.CHOICE_EVERY_MONTH',
  };
  ctrl.busy = false;

  init();

  function init () {
    loadGroups();
    loadSubjects();

    // wait for form to be fully ready (scattered across templates +
    // transclusion + timeouts)
    var unwatch = $scope.$watch('shared.form', function () {
      if (shared.form.date && shared.form.recurrence_end_date) {
        initForm();
        unwatch();
      }
    }, true);

    function initForm () {
      shared.form.recurrence_end_date.$validators.endDate = function (modelValue, viewValue) {
        var date = modelValue || viewValue;
        if (shared.form.isRecurrence &&
          shared.form.recurrence_type &&
          shared.form.recurrence_type.value
        ) {
          return moment(date).isAfter(moment(shared.form.date.value));
        }

        return true;
      };

      // populate dates
      // var date = homeworkManager.getNextCreationDate($stateParams.day, preferences);
      var date = moment($stateParams.day);
      shared.form.date.value = date.toDate();
      shared.form.recurrence_end_date.value = date.toDate();

      // add an empty first homework
      add();
    }
  }

  function add () {
    if (!shared.form.homeworks) {
      shared.form.homeworks = [];
    }

    shared.form.homeworks.push({
      attachments: [],
    });
  }

  function remove (index) {
    if (shared.form.homeworks && shared.form.homeworks[index]) {
      shared.form.homeworks.splice(index, 1);
    }
  }

  function submit () {
    ctrl.busy = true;

    var data = {
      homeworks: [],
    };
    var date = moment(shared.form.date.value);
    var homeworkData = {
      date: date.format('YYYY-MM-DD'),
      recurrence_end_date: date.format('YYYY-MM-DD'),
      recurrence_type: 'ONCE',
      groups: shared.groups,
      homework_subject: shared.form.subject,
    };

    if (shared.form.isRecurrence) {
      homeworkData.recurrence_end_date = moment(shared.form.recurrence_end_date.value).format('YYYY-MM-DD');
      homeworkData.recurrence_type = shared.form.recurrence_type.value;
    }

    angular.forEach(shared.form.homeworks, function (homework) {
      data.homeworks.push(angular.extend({}, homeworkData, {
        name: homework.name.value,
        description: homework.description ? homework.description.value : null,
        helptext: homework.helptext ? homework.helptext.value : null,
        'resource-joined': _.map(homework.attachments, 'id'),
        has_locker: !!homework.has_locker,
      }));
    });

    return Homeworks.post(data)
      .then(function success () {
        shared.success = true;
        if (data.homeworks.length > 1) {
          toast.success('HOMEWORK.FLASH_CREATE_HOMEWORKS_SUCCESS');
        } else {
          toast.success('HOMEWORK.FLASH_CREATE_HOMEWORK_SUCCESS');
        }

        // redirect to overview of first day of week of selected date
        $state.go('app.homework.back.week.content', {
          week: date.clone().isoWeekday(1).format('YYYY-MM-DD'),
        });
      })
      .catch(function error (response) {
        toast.error('HOMEWORK.FLASH_CREATE_HOMEWORK_ERROR');
        console.error(response);
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function loadGroups () {
    navbar.getOrRefreshGroup().then(function (group) {
      shared.groups = [];
      shared.groups.push(group.id);
    });

    Groups.getList({right: 'HOMEWORK_ACCESS_BACK'})
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
      var resource = Homeworks.one('groups').one(''+group.id).all('subjects');
      ctrl.createSubjectUrl = resource.getRestangularUrl();

      return resource.getList()
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

function HomeworkBackCreateSidebarController () {

}

function HomeworkBackCreateStateFactory () {

  return {
    groups: [],
    form: {},
  };

}

})(angular);
