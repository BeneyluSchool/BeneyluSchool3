(function (angular) {
'use strict';

angular.module('bns.homework.back.preferencesControllers', [
  'bns.homework.homeworks',
])

  .controller('HomeworkBackPreferencesActionbar', HomeworkBackPreferencesActionbarController)
  .controller('HomeworkBackPreferencesContent', HomeworkBackPreferencesContentController)
  .factory('homeworkBackPreferencesState', HomeworkBackPreferencesStateFactory)

;

function HomeworkBackPreferencesActionbarController ($scope, preferences, homeworkBackPreferencesState) {

  $scope.shared = homeworkBackPreferencesState;

  var ctrl = this;
  ctrl.preferences = preferences;

}

function HomeworkBackPreferencesContentController (_, $scope, arrayUtils, toast, Homeworks, preferences, homeworkBackPreferencesState) {

  $scope.shared = homeworkBackPreferencesState;

  var ctrl = this;
  ctrl.preferences = preferences;
  ctrl.form = {
    days: [],
  };
  ctrl.submit = submit;

  init();

  function init () {
    ctrl.allDays = _.map(['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], function (day) {
      return {
        value: day,
        label: 'HOMEWORK.LABEL_DAY_'+day,
      };
    });

    arrayUtils.merge(ctrl.form.days, preferences.days);
    ctrl.form.activate_validation = preferences.activate_validation;
    ctrl.form.show_tasks_done = preferences.show_tasks_done;
  }

  function submit () {
    return Homeworks.one('preferences').patch(ctrl.form)
      .then(function success (updatedPreferences) {
        toast.success('HOMEWORK.FLASH_SAVE_PREFERENCES_SUCCESS');
        preferences.days.splice(0, preferences.days.length);
        arrayUtils.merge(preferences.days, updatedPreferences.days);
        preferences.activate_validation = updatedPreferences.activate_validation;
        preferences.show_tasks_done = updatedPreferences.show_tasks_done;
      })
      .catch(function error (response) {
        toast.error('HOMEWORK.FLASH_SAVE_PREFERENCES_ERROR');
        throw response;
      })
    ;
  }

}

function HomeworkBackPreferencesStateFactory () {

  return {
    form: {},
  };

}

})(angular);
