'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc overview
   * @name MediaLibraryNavigationNodeCtrl
   * @kind function
   *
   * @description Simple controller for nodes in the media library navigation
   * tree.
   */
  .controller('MediaLibraryNavigationNodeCtrl', function ($scope, $translate, mediaLibraryManager) {
    this.init = function () {

      // init options for the jQuery ui draggable
      var helperText;
      $translate('MEDIA_LIBRARY.MOVE').then(function (translation) {
        helperText = translation + ' \'' + $scope.node.label + '\'';
      });
      $scope.draggableUiOptions = {
        appendTo: 'body',
        helper: function() {
          return '<div class="navigation-drag-helper">'+helperText+'</div>';
        },
        distance: 6
      };
    };

    this.init();

    $scope.isExpanded = function () {
      return !!$scope.tree.isExpanded($scope.node);
    };

    $scope.toggleExpanded = function () {
      $scope.tree.toggleExpanded($scope.node);
    };

    // Gets a css class based on the node type
    $scope.nodeTypeClass = function () {
      if (mediaLibraryManager.isFile($scope.node)) {
        return 'file';
      }

      if (mediaLibraryManager.isExternal($scope.node)) {
        return 'shop';
      }

      if (mediaLibraryManager.isUserFolder($scope.node)) {
        return 'folder';
      }

      if (mediaLibraryManager.isLockerFolder($scope.node)) {
        return 'locker';
      }

      if (mediaLibraryManager.isSchoolFolder($scope.node)) {
        return 'school';
      }

      if (mediaLibraryManager.isClassFolder($scope.node)) {
        return 'class';
      }

      if (mediaLibraryManager.isTeamFolder($scope.node)) {
        return 'team';
      }

      if (mediaLibraryManager.isPartnershipFolder($scope.node)) {
        return 'partnership';
      }

      if (mediaLibraryManager.isGroupFolder($scope.node)) {
        // TODO: default group folder icon
        return 'class';
      }

      if (mediaLibraryManager.isTrash($scope.node)) {
        return 'trash';
      }

      if (mediaLibraryManager.isFavorites($scope.node)) {
        return 'favorite';
      }

      if (mediaLibraryManager.isRecents($scope.node)) {
        return 'recent';
      }
    };

  });
