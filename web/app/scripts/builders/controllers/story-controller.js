(function (angular) {
'use strict';

angular.module('bns.builders.storyController', [])

  .controller('BuildersStory', BuildersStoryController)

  // close your eyes!
  .constant('BUILDERS_STORY_NEW_FILES', {
    1: 'carnet-beneylu-jim.pdf',
    2: 'carnet-brabrabra.pdf',
    3: 'carnet-carla.pdf',
    4: 'carnet-projet-classe.pdf',
  })

;

function BuildersStoryController ($state, $stateParams, $timeout, Restangular, dialog, toast, BUILDERS_STORY_NEW_FILES) {

  var ctrl = this;
  ctrl.story = $stateParams.story;
  ctrl.createBook = createBook;
  ctrl.deleteBook = deleteBook;
  ctrl.print = print;
  ctrl.busy = false;

  init();

  function init () {
    ctrl.file = BUILDERS_STORY_NEW_FILES[ctrl.story];
    ctrl.busy = true;

    return Restangular.all('builders').all('books').getList({story: ctrl.story})
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

  function createBook () {
    Restangular.all('builders').all('books').post({story: ctrl.story})
      .then(function success (book) {
        toast.success('BUILDERS.FLASH_CREATE_BOOK_SUCCESS');
        ctrl.books.splice(0, 0, book);

        return $state.go('^.book', {id: book.id});
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_CREATE_BOOK_ERROR');
        throw response;
      })
    ;
  }

  function deleteBook (event, book) {
    return dialog.confirm({
      title: 'BUILDERS.TITLE_DELETE_BOOK',
      content: 'BUILDERS.DESCRIPTION_DELETE_BOOK',
      cancel: 'BUILDERS.BUTTON_CANCEL',
      ok: 'BUILDERS.BUTTON_DELETE_BOOK',
      intent: 'warn',
      targetEvent: event,
    })
      .then(doDeleteBook)
    ;

    function doDeleteBook () {
      return book.remove()
        .then(function success () {
          toast.success('BUILDERS.FLASH_DELETE_BOOK_SUCCESS');
          var idx = ctrl.books.indexOf(book);
          if (idx > -1) {
            ctrl.books.splice(idx, 1);
          }
        })
        .catch(function error (response) {
          toast.error('BUILDERS.FLASH_DELETE_BOOK_ERROR');
          throw response;
        })
      ;
    }
  }

  function print (color) {
    return console.info('TODO: print', color);
  }

}

})(angular);
