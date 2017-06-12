(function (angular) {
'use strict';

angular.module('bns.lsu.editRecordHeaderController', [])

  .controller('LsuEditRecordHeader', LsuEditRecordHeaderController)

;

function LsuEditRecordHeaderController ($rootScope, $scope, Restangular, $mdUtil, dialog, toast, Groups, bnsLoader) {

  var ctrl = this;
  ctrl.hide = dialog.hide;

  init();
  setupWatchers();

  function init () {
    ctrl.model = {
      user: {
        first_name: ctrl.record.user.first_name,
        last_name: ctrl.record.user.last_name,
        ine: ctrl.record.user.ine,
      },
    };

    Groups.getCurrent()
      .then(function success (classroom) {
        ctrl.classroom = classroom;
        ctrl.model.classroom = {
          onde_id: classroom.onde_id,
        };
      })
    ;

    return Restangular.one('groups/current', 'school').get()
      .then(function success (school) {
        ctrl.school = school;
        ctrl.model.school = {
          uai: school.uai
        };
      })
      .catch(function error (response) {
        toast.error('LSU.FLASH_GET_SCHOOL_ERROR');
        throw response;
      })
    ;
  }

  function setupWatchers () {
    $scope.$watch('ctrl.model.user', $mdUtil.debounce(updateUser, 1000), true);
    $scope.$watch('ctrl.model.classroom', $mdUtil.debounce(updateClassroom, 1000), true);
    $scope.$watch('ctrl.model.school', $mdUtil.debounce(updateSchool, 1000), true);
  }

  function updateUser (newData, oldData) {
    if (!(newData && oldData && newData !== oldData)) {
      return;
    }

    return bnsLoader.observePromise(Restangular.one('users', ctrl.record.user.id).patch(newData)
      .then(success)
      .catch(error)
    );
    function success (user) {
      $rootScope.$emit('user.updated', user);
    }
    function error () {
      toast.error('LSU.FLASH_UPDATE_USER_ERROR');
    }
  }

  function updateSchool (newData, oldData) {
    if (!(newData && oldData && newData !== oldData)) {
      return;
    }

    return bnsLoader.observePromise(Restangular.one('groups/current', 'school').patch(newData)
      .then(success)
      .catch(error)
    );
    function success (school) {
      angular.merge(ctrl.school, school);
    }
    function error () {
      toast.error('LSU.FLASH_UPDATE_SCHOOL_ERROR');
    }
  }

  function updateClassroom (newData, oldData) {
    if (!(newData && oldData && newData !== oldData)) {
      return;
    }

    return bnsLoader.observePromise(Restangular.one('groups', ctrl.classroom.id).patch(newData)
      .then(success)
      .catch(error)
    );
    function success (classroom) {
      angular.merge(ctrl.classroom, classroom);
    }
    function error () {
      toast.error('LSU.FLASH_UPDATE_CLASSROOM_ERROR');
    }
  }

}

})(angular);
