(function (angular) {
'use strict';

angular.module('bns.builders.backBooksControllers', [])

  .controller('BuildersBackBooksSidebar', BuildersBackBooksSidebarController)
  .controller('BuildersBackBooksContent', BuildersBackBooksContentController)
  .factory('buildersBackBooksState', BuildersBackBooksStateFactory)

;

function BuildersBackBooksSidebarController ($scope, buildersBackBooksState) {

  $scope.shared = buildersBackBooksState;

  var ctrl = this;
  ctrl.statuses = [
    { value: 0, label: 'BUILDERS.LABEL_STATUS_STARTED'  },
    { value: 1, label: 'BUILDERS.LABEL_STATUS_COMPLETE' },
  ];
  ctrl.stories = [
    { value: 1, label: 'BUILDERS.STORY_1' },
    { value: 2, label: 'BUILDERS.STORY_2' },
    { value: 3, label: 'BUILDERS.STORY_3' },
    { value: 4, label: 'BUILDERS.STORY_4' },
  ];

}

function BuildersBackBooksContentController ($scope, Restangular, toast, buildersBackBooksState) {

  var shared = $scope.shared = buildersBackBooksState;

  var ctrl = this;
  ctrl.countAnswers = countAnswers;
  ctrl.busy = false;

  init();

  function init () {
    $scope.$watch('shared.filters', refreshBooks, true);

    return refreshBooks();
  }

  function refreshBooks () {
    if (ctrl.busy) {
      return; // avoid duplicate call (init + watch setup)
    }
    ctrl.busy = true;

    return Restangular.all('builders').all('books').all('group').getList(shared.filters)
      .then(function success (books) {
        ctrl.books = books;
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_GET_BOOKS_ERROR');
        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function countAnswers (book) {
    var count = 0;
    [1, 2, 3, 4].forEach(function (step) {
      if (book.answers[step]) {
        count++;
      }
    });

    return count;
  }

}

function BuildersBackBooksStateFactory () {

  return {
    filters: {
      is_complete: null,
    },
  };

}

})(angular);
