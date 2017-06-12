(function (angular) {
'use strict';

angular.module('bns.homework.back.subjectsControllers', [
  'bns.homework.homeworks',
])

  .controller('HomeworkBackSubjectsActionbar', HomeworkBackSubjectsActionbarController)
  .controller('HomeworkBackSubjectsContent', HomeworkBackSubjectsContentController)
  .controller('HomeworkBackSubjectFormDialog', HomeworkBackSubjectFormDialogController)
  .controller('HomeworkBackSubjectDeleteDialog', HomeworkBackSubjectDeleteDialogController)

;

function HomeworkBackSubjectsActionbarController ($rootScope, dialog) {

  var ctrl = this;
  ctrl.showCreateDialog = showCreateDialog;

  function showCreateDialog ($event) {
    return dialog.custom({
      templateUrl: 'views/homework/back/dialog/subject-form.html',
      controller: 'HomeworkBackSubjectFormDialog',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        subject: {},
      },
      targetEvent: $event,
      focusOnOpen: false,
      clickOutsideToClose: true,
    })
      .then(function (subject) {
        $rootScope.$emit('homework.subject.created', subject);
      })
    ;
  }

}

function HomeworkBackSubjectsContentController (_, $scope, $rootScope, $mdUtil,
  arrayUtils, dialog, toast, navbar, Homeworks) {

  var debouncedUpdateSubjectsSort = $mdUtil.debounce(updateSubjectsSort, 1000);

  var ctrl = this;
  ctrl.showEditDialog = showEditDialog;
  ctrl.showDeleteConfirm = showDeleteConfirm;
  ctrl.sortableConfig = {
    handle: '.drag-handle',
    onUpdate: onSortUpdate,
  };

  init();

  function init () {
    var unregisterRootscopeListener = $rootScope.$on('homework.subject.created', onSubjectCreated);
    $scope.$on('$destroy', function cleanup () {
      unregisterRootscopeListener();
    });

    return navbar.getOrRefreshGroup().then(function (group) {
      var resource = Homeworks.one('groups').one(''+group.id).all('subjects');
      ctrl.createSubjectUrl = resource.getRestangularUrl();

      return resource.getList()
        .then(function (subjects) {
          ctrl.subjects = subjects;
        })
      ;
    })
      .catch(function error (response) {
        toast.error('HOMEWORK.FLASH_LOAD_SUBJECTS_ERROR');
        throw response;
      })
    ;
  }

  function showEditDialog (subject, $event) {
    return dialog.custom({
      templateUrl: 'views/homework/back/dialog/subject-form.html',
      controller: 'HomeworkBackSubjectFormDialog',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        subject: subject,
      },
      targetEvent: $event,
      focusOnOpen: false,
      clickOutsideToClose: true,
    });
  }

  function showDeleteConfirm (subject, $event) {
    return dialog.custom({
      templateUrl: 'views/homework/back/dialog/subject-delete.html',
      controller: 'HomeworkBackSubjectDeleteDialog',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        subject: subject,
      },
      targetEvent: $event,
      clickOutsideToClose: true,
    })
      .then(function () {
        // remove delete object from local collection
        arrayUtils.remove(ctrl.subjects, subject);
      })
    ;
  }

  function onSubjectCreated ($event, subject) {
    ctrl.subjects.push(subject);
  }

  function onSortUpdate (event) {
    debouncedUpdateSubjectsSort(event.models);
  }

  function updateSubjectsSort (subjects) {
    var ids = _.map(subjects, 'id');

    return ctrl.subjects.all('sort').patch({ids: ids})
      .then(success)
      .catch(error)
    ;

    function success () {
      toast.success('HOMEWORK.FLASH_REORDER_SUBJECTS_SUCCESS');
    }

    function error (response) {
      toast.error('HOMEWORK.FLASH_REORDER_SUBJECTS_ERROR');
      throw response;
    }
  }

}

function HomeworkBackSubjectFormDialogController ($mdDialog, toast, navbar, Homeworks, subject) {

  var ctrl = this;
  ctrl.abort = function () { return $mdDialog.cancel(); };
  ctrl.model = angular.copy(subject);
  ctrl.save = save;

  function save () {
    if (subject.id) {
      return edit();
    } else {
      return create();
    }
  }

  function create () {
    return navbar.getOrRefreshGroup().then(function (group) {
      return Homeworks.one('groups').one(''+group.id).all('subjects')
        .post(ctrl.model)
        .then(success)
        .catch(error)
      ;
    });

    function success (result) {
      // update local object with remote data
      angular.extend(subject, result);
      toast.success('HOMEWORK.FLASH_CREATE_SUBJECT_SUCCESS');

      return $mdDialog.hide(subject);
    }

    function error (response) {
      toast.error('HOMEWORK.FLASH_CREATE_SUBJECT_ERROR');

      throw response;
    }
  }

  function edit () {
    return Homeworks.one('subjects').one(''+subject.id).patch(ctrl.model)
      .then(success)
      .catch(error)
    ;

    function success () {
      // update actual object with local changes
      angular.extend(subject, ctrl.model);
      toast.success('HOMEWORK.FLASH_EDIT_SUBJECT_SUCCESS');

      return $mdDialog.hide(subject);
    }

    function error (response) {
      toast.error('HOMEWORK.FLASH_EDIT_SUBJECT_ERROR');

      throw response;
    }
  }

}

function HomeworkBackSubjectDeleteDialogController ($mdDialog, toast, Homeworks, subject) {

  var ctrl = this;
  ctrl.abort = function () { return $mdDialog.cancel(); };
  ctrl.remove = remove;

  function remove () {

    return Homeworks.one('subjects').one(''+subject.id).remove()
      .then(success)
      .catch(error)
    ;

    function success () {
      toast.success('HOMEWORK.FLASH_DELETE_SUBJECT_SUCCESS');

      return $mdDialog.hide(subject);
    }

    function error (response) {
      toast.error('HOMEWORK.FLASH_DELETE_SUBJECT_ERROR');

      throw response;
    }
  }

}

})(angular);
