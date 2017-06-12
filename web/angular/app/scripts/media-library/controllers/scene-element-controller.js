'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc overview
   * @name MediaLibrarySceneElementCtrl
   * @kind function
   *
   * @description Simple controller for elements in the media library scene.
   */
  .controller('MediaLibrarySceneElementCtrl', function ($scope, $translate) {
    this.init = function () {

      // init options for the jQuery ui draggable
      var helperText;
      $translate('MEDIA_LIBRARY.MOVE').then(function (translation) {
        helperText = translation + ' \'' + $scope.element.label + '\'';
      });
      $scope.draggableUiOptions = {
        appendTo: 'body',
        helper: function() {
          return '<div class="navigation-drag-helper">'+helperText+'</div>';
        },
        distance: 6,
        cursor: 'move',
        cursorAt: { top: 10, left: 10}
      };
    };

    this.init();
  });
