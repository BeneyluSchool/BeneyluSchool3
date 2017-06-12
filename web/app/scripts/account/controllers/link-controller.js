(function (angular) {
'use strict';

angular.module('bns.account.linkController', [
  'bns.user.users',
  'restangular',
])

  .controller('AccountLink', AccountLinkController)
  .controller('AccountLinkForgotLogin', AccountLinkForgotLoginController)
  .controller('AccountLinkConfirmPupilRecovery', AccountLinkConfirmPupilRecoveryController)

;

function AccountLinkController ($scope, $q, $window, $state, $translate, moment, Restangular, Groups, Users, dialog) {

  var RECOVERABLE_CLASSROOM_DATA = [
    { value: 'BLOG', label: 'MODULE.BLOG' },
    { value: 'MEDIA_LIBRARY', label: 'MODULE.MEDIA_LIBRARY' },
  ];
  var RECOVERABLE_CLASSROOM_DATA_ALL = [
    { value: 'BLOG', label: 'MODULE.BLOG' },
    { value: 'MEDIA_LIBRARY', label: 'MODULE.MEDIA_LIBRARY' },
    { value: 'CALENDAR', label: 'MODULE.CALENDAR' },
    { value: 'HOMEWORK', label: 'MODULE.HOMEWORK' },
    { value: 'LIAISONBOOK', label: 'MODULE.LIAISONBOOK' },
  ];
  var cleanPreventExit;
  var ctrl = this;
  ctrl.oldUsername = null;
  ctrl.oldPassword = null;
  ctrl.backToSchoolDate = null;
  ctrl.schools = null;      // list of previous schools
  ctrl.classrooms = null;   // list of previous classrooms
  ctrl.configuration = {
    user: null,             // previous user, to get data from
    school: null,           // previous school, to get data from
    classroom: null,        // previous classroom, to get data from
    user_data: [],           // user data to be recovered
    school_data: [],         // school data to be recovered
    classroom_data: [],      // classroom data to be recovered
  };
  ctrl.recoverableUserData = [
    { value: 'MESSAGING', label: 'MODULE.MESSAGING' },
    { value: 'MEDIA_LIBRARY', label: 'MODULE.MEDIA_LIBRARY' },
    { value: 'PROFILE', label: 'MODULE.PROFILE' },
  ];
  ctrl.recoverableClassroomData = angular.copy(RECOVERABLE_CLASSROOM_DATA);
  ctrl.recoverableSchoolData = [
    { value: 'BLOG', label: 'MODULE.BLOG' },
    { value: 'MINISITE', label: 'MODULE.MINISITE' },
    { value: 'MEDIA_LIBRARY', label: 'MODULE.MEDIA_LIBRARY' },
    { value: 'LIAISONBOOK', label: 'MODULE.LIAISONBOOK' },
    { value: 'CALENDAR', label: 'MODULE.CALENDAR' },
  ];
  ctrl.pupilMatchesSortableConfig = {
    animation: 150,
    group: {
      name: 'pupils',
      put: function (to) {
        return !to.el.children.length;
      }
    },
  };
  ctrl.oldPupilsSortableConfig = {
    animation: 150,
    group: {
      name: 'pupils',
    },
  };

  ctrl.canRecoverPupils = true;
  ctrl.canRecoverAllApps = true;
  ctrl.canViewProgressScreen = true;

  ctrl.skipRecoverUser = skipRecoverUser;
  ctrl.validateOldCredentials = validateOldCredentials;
  ctrl.showForgotLoginDialog = showForgotLoginDialog;
  ctrl.stepRecoverPreviousSchool = stepRecoverPreviousSchool;
  ctrl.selectSchool = selectSchool;
  ctrl.skipRecoverSchool = skipRecoverSchool;
  ctrl.stepRecoverPreviousClassroom = stepRecoverPreviousClassroom;
  ctrl.selectClassroom = selectClassroom;
  ctrl.skipRecoverClassroom = skipRecoverClassroom;
  ctrl.doNotRecoverSchoolData = doNotRecoverSchoolData;
  ctrl.doRecoverSchoolData = doRecoverSchoolData;
  ctrl.doNotRecoverData = doNotRecoverData;
  ctrl.doRecoverData = doRecoverData;
  ctrl.confirmPupilMatches = confirmPupilMatches;

  /*
   * 1: select previous user
   * 2: select previous school
   * 3: select previous classroom to recover
   * 4: select data to recover
   * 5: map pupils
   * 6: process information
   */
  ctrl.step = null;

  ctrl.busy = false;

  init();

  function init () {
    cleanPreventExit = $scope.$on('$locationChangeStart', function (event) {
      if (!$window.confirm($translate.instant('ACCOUNT.WARNING_CONFIRM_LEAVE_PROCESS'))) {
        event.preventDefault();
      }
    });

    // set the back to school date to the first day of last september (0-indexed)
    ctrl.backToSchoolDate = moment();
    while (ctrl.backToSchoolDate.month() !== 8) {
      ctrl.backToSchoolDate.subtract(1, 'month');
    }
    ctrl.backToSchoolDate.startOf('month');

    ctrl.busy = true;

    return $q.all({ group: Groups.getCurrent(), me: Users.me() })
      .then(function (results) {
        ctrl.group = results.group;
        ctrl.me = results.me;

        if (ctrl.me.aaf_id) {
          return Restangular.one('users-link').one('previous-groups').all('classroom').getList()
            .then(function (groups) {
              if (groups.length) {
                ctrl.canRecoverPupils = false;
                ctrl.canRecoverAllApps = false;
                ctrl.canViewProgressScreen = false;

                return stepRecoverPreviousClassroom();
              } else if (ctrl.backToSchoolDate.isBefore(ctrl.me.created_at)) {
                // account created after the back to school period, ask if has previous user
                return stepRecoverPreviousUser();
              } else {
                // old account without previous data, skip recovery process
                ctrl.canViewProgressScreen = false;

                return doRecovery();
              }
            })
          ;
        } else {
          return stepRecoverPreviousClassroom();
        }
      })
      .finally(function () {
        ctrl.busy = false;
      })
    ;
  }

  function cleanStep () {
    ctrl.step = null;
    ctrl.confirm = null;
    ctrl.back = null;
    ctrl.classrooms = null;
  }

  function stepRecoverPreviousUser () {
    cleanStep();
    ctrl.step = 'user';
    ctrl.configuration.user = null;
  }

  function stepRecoverPreviousSchool () {
    cleanStep();
    ctrl.step = 'school';
    ctrl.configuration.school = null;
    ctrl.busy = true;

    var data = {};
    if (ctrl.configuration.user) {
      data.user_login = ctrl.configuration.user.login;
      data.user_password = ctrl.configuration.user.password;
    }

    return Users.hasRight('SCHOOL_ACCESS_BACK')
      .then(function yep () {
        return Restangular.one('users-link').one('previous-groups').all('school').getList(data)
          .then(function (groups) {
            ctrl.schools = groups;
          })
          .finally(function () {
            ctrl.busy = false;
          })
        ;
      })
      .catch(function nope () {
        return stepRecoverPreviousClassroom();
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function stepRecoverSchoolData () {
    cleanStep();
    ctrl.step = 'school-data';
  }

  function stepRecoverPreviousClassroom () {
    cleanStep();
    ctrl.step = 'classroom';
    ctrl.configuration.classroom = null;
    ctrl.busy = true;

    var data = {};
    if (ctrl.configuration.user) {
      data.user_login = ctrl.configuration.user.login;
      data.user_password = ctrl.configuration.user.password;
    }

    return Restangular.one('users-link').one('previous-groups').all('classroom').getList(data)
      .then(function (groups) {
        ctrl.classrooms = groups;
      })
      .finally(function () {
        ctrl.busy = false;
      })
    ;
  }

  function stepRecoverData () {
    cleanStep();
    ctrl.step = 'classroom-data';
    if (ctrl.canRecoverAllApps) {
      ctrl.recoverableClassroomData = angular.copy(RECOVERABLE_CLASSROOM_DATA_ALL);
    }
  }

  function stepRecoverPupils () {
    // no previous classroom selected
    if (!ctrl.configuration.classroom) {
      return doRecovery();
    }

    if (!ctrl.canRecoverPupils) {
      return doRecovery();
    }

    cleanStep();
    ctrl.step = 'classroom-pupils';
    ctrl.confirm = {
      label: 'ACCOUNT.BUTTON_SUBMIT',
      action: showConfirmRecoveryDialog,
    };

    return loadPupils();
  }

  function skipRecoverUser () {
    ctrl.canViewProgressScreen = false;

    return doRecovery();
  }

  function showForgotLoginDialog ($event) {
    return dialog.custom({
      templateUrl: 'views/account/link/forgot-login-dialog.html',
      controller: 'AccountLinkForgotLogin',
      controllerAs: 'ctrl',
      clickOutsideToClose: true,
      targetEvent: $event,
    });
  }

  function validateOldCredentials () {
    if (!(ctrl.oldUsername && ctrl.oldPassword)) {
      return;
    }

    ctrl.errorMessage = '';

    return Restangular.one('users-link').one('users').one('by-credentials').post(null, {
      login: ctrl.oldUsername,
      password: ctrl.oldPassword,
    })
      .then(function success (user) {
        ctrl.configuration.user = user;
        ctrl.configuration.user.login = ctrl.oldUsername;
        ctrl.configuration.user.password = ctrl.oldPassword;

        return stepRecoverPreviousSchool();
      })
      .catch(function error (response) {
        if (404 === response.status) {
          ctrl.errorMessage = 'ACCOUNT.FLASH_ERROR_INVALID_OR_NOT_FOUND';
        }
      })
    ;
  }

  function selectSchool (school) {
    ctrl.configuration.school = school;

    return stepRecoverSchoolData();
  }

  function skipRecoverSchool () {
    return dialog.confirm({
      intent: 'warn',
      content: 'ACCOUNT.DESCRIPTION_NO_RECOVER_SCHOOL',
      cancel: 'ACCOUNT.BUTTON_CANCEL',
      ok: 'ACCOUNT.BUTTON_CONFIRM',
    })
      .then(function () {
        ctrl.configuration.school_data.splice(0, ctrl.configuration.school_data.length);
        ctrl.configuration.school = null;

        return stepRecoverPreviousClassroom();
      })
    ;
  }

  function selectClassroom (classroom) {
    ctrl.configuration.classroom = classroom;

    return stepRecoverData();
  }

  function skipRecoverClassroom () {
    if (ctrl.configuration.user) {
      return stepRecoverData();
    } else {
      return dialog.confirm({
        intent: 'warn',
        content: 'ACCOUNT.DESCRIPTION_NO_RECOVER',
        cancel: 'ACCOUNT.BUTTON_CANCEL',
        ok: 'ACCOUNT.BUTTON_CONFIRM',
      })
        .then(function () {
          ctrl.configuration.user_data.splice(0, ctrl.configuration.user_data.length);
          ctrl.configuration.classroom_data.splice(0, ctrl.configuration.classroom_data.length);
          ctrl.configuration.classroom = null;

          return doRecovery();
        })
      ;
    }
  }

  function doNotRecoverSchoolData () {
    return dialog.confirm({
      intent: 'warn',
      content: 'ACCOUNT.DESCRIPTION_NO_RECOVER_SCHOOL_DATA',
      cancel: 'ACCOUNT.BUTTON_CANCEL',
      ok: 'ACCOUNT.BUTTON_CONFIRM',
    })
      .then(function () {
        ctrl.configuration.school_data.splice(0, ctrl.configuration.school_data.length);

        return stepRecoverPreviousClassroom();
      })
    ;
  }

  function doRecoverSchoolData () {
    if (!(ctrl.configuration.school_data.length)) {
      return doNotRecoverSchoolData();
    }

    var apps = ctrl.configuration.school_data
      .map(function (app) {
        return $translate.instant('MODULE.' + app);
      })
    ;

    var content = $translate.instant('ACCOUNT.DESCRIPTION_RECOVERED_SCHOOL_APPS', {
      apps_list: '<ul><li>' + apps.join('</li><li>') + '</li></ul>',
      label: ctrl.configuration.school.label,
    });

    return dialog.confirm({
      content: content,
      cancel: 'ACCOUNT.BUTTON_CANCEL',
      ok: 'ACCOUNT.BUTTON_CONFIRM',
    })
      .then(function () {
        return stepRecoverPreviousClassroom();
      })
    ;
  }

  function doNotRecoverData () {
    return dialog.confirm({
      intent: 'warn',
      content: 'ACCOUNT.DESCRIPTION_NO_RECOVER_DATA',
      cancel: 'ACCOUNT.BUTTON_CANCEL',
      ok: 'ACCOUNT.BUTTON_CONFIRM',
    })
      .then(function () {
        ctrl.configuration.user_data.splice(0, ctrl.configuration.user_data.length);
        ctrl.configuration.classroom_data.splice(0, ctrl.configuration.classroom_data.length);

        return stepRecoverPupils();
      })
    ;
  }

  function doRecoverData () {
    if (!(ctrl.configuration.user_data.length || ctrl.configuration.classroom_data.length)) {
      return doNotRecoverData();
    }

    var apps = ctrl.configuration.user_data
      .concat(ctrl.configuration.classroom_data)
      .map(function (app) {
        return $translate.instant('MODULE.' + app);
      })
    ;

    var content = $translate.instant('ACCOUNT.DESCRIPTION_RECOVERED_APPS', {
      apps_list: '<ul><li>' + apps.join('</li><li>') + '</li></ul>',
    });

    if (ctrl.configuration.classroom) {
      content += $translate.instant('ACCOUNT.DESCRIPTION_FROM_CLASSROOM', {
        label: ctrl.configuration.classroom.label,
      });
    }

    return dialog.confirm({
      content: content,
      cancel: 'ACCOUNT.BUTTON_CANCEL',
      ok: 'ACCOUNT.BUTTON_CONFIRM',
    })
      .then(function () {
        return stepRecoverPupils();
      })
    ;
  }

  function loadPupils () {
    ctrl.busy = true;

    return Restangular.one('users-link').all('pupils-map').get('', {
      new_group_id: ctrl.group.id,
      old_group_id: ctrl.configuration.classroom.id,
    })
      .then(function success (map) {
        // create a map of arrays (for sortable to work properly)
        ctrl.pupilMatches = {};
        for (var i = 0; i < map.new.length; i++) {
          var id = map.new[i].id;
          ctrl.pupilMatches[id] = [];
          if (map.matches[id]) {
            ctrl.pupilMatches[id].push(map.matches[id]);
          }
        }

        ctrl.pupilsMap = map;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function confirmPupilMatches () {
    return showConfirmRecoveryDialog();
  }

  function showConfirmRecoveryDialog () {
    return dialog.custom({
      templateUrl: 'views/account/link/confirm-pupil-recovery-dialog.html',
      controller: 'AccountLinkConfirmPupilRecovery',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        pupilsMap: ctrl.pupilsMap,
        pupilMatches: ctrl.pupilMatches,
        configuration: ctrl.configuration,
      }
    })
      .then(doRecovery)
    ;
  }

  function doRecovery () {
    cleanStep();

    ctrl.configuration.pupils = {};
    angular.forEach(ctrl.pupilMatches, function (pupils, id) {
      if (pupils[0]) {
        ctrl.configuration.pupils[id] = pupils[0].id;
      }
    });

    var data = {
      user_data: ctrl.configuration.user_data,
      school_id: ctrl.configuration.school ? ctrl.configuration.school.id : null,
      school_data: ctrl.configuration.school_data,
      classroom_id: ctrl.configuration.classroom ? ctrl.configuration.classroom.id : null,
      classroom_data: ctrl.configuration.classroom_data,
      pupils_map: ctrl.configuration.pupils,
    };

    if (ctrl.configuration.user) {
      data.user_login = ctrl.configuration.user.login;
      data.user_password = ctrl.configuration.user.password;
    }

    return Restangular.all('users-link').one('recovery').post(null, data)
      .then(function success (result) {
        if (!ctrl.canViewProgressScreen) {
          return $state.go('classroom');
        }
        ctrl.step = 'overview';
        ctrl.isProcessing = !!result.recovery;
      })
    ;

  }

}

function AccountLinkForgotLoginController (Restangular, dialog, toast) {

  var ctrl = this;
  ctrl.confirm = function () { return dialog.hide(); };
  ctrl.cancel = function () { return dialog.cancel(); };
  ctrl.submit = submit;
  ctrl.busy = false;
  ctrl.isSuccess = false;
  ctrl.isError = false;

  function submit () {
    ctrl.busy = true;
    ctrl.isSuccess = false;
    ctrl.isError = false;

    return Restangular.one('users').one('password').post('reset', {
      identifier: ctrl.identifier,
    })
      .then(function success (response) {
        ctrl.isSuccess = !!response.sent;
      })
      .catch(function error (response) {
        if (404 === response.status) {
          ctrl.isError = true;
        } else {
          toast.error('ACCOUNT.FLASH_RESET_PASSWORD_ERROR');
          throw response;
        }
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

function AccountLinkConfirmPupilRecoveryController (_, moment, dialog, $scope) {

  var ctrl = this;
  ctrl.confirm = function () { return dialog.hide(); };
  ctrl.cancel = function () { return dialog.cancel(); };

  ctrl.hasMatches = _.some(ctrl.pupilMatches, function (match) {
    return (match && match.length);
  });

  $scope.moment = moment;

}

})(angular);
