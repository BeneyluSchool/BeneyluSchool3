(function (angular) {
'use strict';

angular.module('bns.homework.back.editControllers', [
  'bns.user.groups',
])

  .controller('HomeworkBackEditActionbar', HomeworkBackEditActionbarController)
  .controller('HomeworkBackEditContent', HomeworkBackEditContentController)
  .controller('HomeworkBackEditSidebar', HomeworkBackEditSidebarController)
  .factory('homeworkBackEditState', HomeworkBackEditStateFactory)

;

function HomeworkBackEditActionbarController ($scope, homeworkBackEditState) {

  $scope.shared = homeworkBackEditState;

}

function HomeworkBackEditContentController (
  _, moment, $scope, $state, arrayUtils, toast, Groups, homework, homeworkBackEditState,
  navbar, Homeworks
) {

  var shared = $scope.shared = homeworkBackEditState;

  var ctrl = this;
  ctrl.homework = homework;
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
      if (shared.form.date && shared.form.recurrence_end_date && shared.form.description) {
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

      shared.groups = [];

      // basic form values
      shared.form.date.value = moment(homework.date).toDate();
      shared.form.name.value = homework.name;
      shared.form.description.value = homework.description;
      shared.form.helptext.value = homework.helptext;
      shared.form.has_locker.value = homework.has_locker;
      shared.form.recurrence_type.value = homework.recurrence_type;
      shared.form.recurrence_end_date.value = moment(homework.recurrence_end_date).toDate();

      // custom values
      shared.form.subject = homework._embedded.subject ? homework._embedded.subject.id : null;
      shared.form.isRecurrence = homework.recurrence_type !== 'ONCE';
      arrayUtils.merge(shared.groups, homework.groups_ids);
      shared.form.attachments = homework._embedded.attachments;
    }
  }

  function submit () {
    ctrl.busy = true;

    var date = moment(shared.form.date.value);
    var data = {
      date: date.format('YYYY-MM-DD'),
      recurrence_end_date: date.format('YYYY-MM-DD'),
      recurrence_type: 'ONCE',
      groups: shared.groups,
      name: shared.form.name.value,
      description: shared.form.description.value,
      helptext: shared.form.helptext.value,
      'resource-joined': _.map(shared.form.attachments, 'id'),
      has_locker: shared.form.has_locker.value,
      homework_subject: shared.form.subject,
    };

    if (shared.form.isRecurrence) {
      data.recurrence_end_date = moment(shared.form.recurrence_end_date.value).format('YYYY-MM-DD');
      data.recurrence_type = shared.form.recurrence_type.value;
    }

    return homework.patch(data)
      .then(function success () {
        toast.success('HOMEWORK.FLASH_EDIT_HOMEWORK_SUCCESS');
        $state.go('app.homework.back.week.content', {
          week: date.clone().isoWeekday(1).format('YYYY-MM-DD'),
        });
      })
      .catch(function error (response) {
        toast.error('HOMEWORK.FLASH_EDIT_HOMEWORK_ERROR');
        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function loadGroups () {
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

function HomeworkBackEditSidebarController () {

}

function HomeworkBackEditStateFactory () {

  return {
    groups: [],
    form: {},
  };

}

})(angular);
