'use strict';

angular.module('bns.mediaLibrary')

  .controller('MediaLibraryTopbarCtrl', function ($scope, $rootScope) {
    var ctrl = this;

    ctrl.init = function () {};

    ctrl.init();

    $scope.deleteSelection = function () {
      $rootScope.$broadcast('mediaLibrary.selection.deleteRequest');
    };

    $scope.toggleFavoriteSelection = function () {
      $rootScope.$broadcast('mediaLibrary.selection.toggleFavoriteRequest');
    };

    $scope.togglePrivateSelection = function () {
      $rootScope.$broadcast('mediaLibrary.selection.togglePrivateRequest');
    };

    $scope.moveSelection = function () {
      $rootScope.$broadcast('mediaLibrary.selection.moveRequest');
    };

    $scope.copySelection = function () {
      $rootScope.$broadcast('mediaLibrary.selection.copyRequest');
    };

    $scope.deleteContextSelection = function () {
      $rootScope.$emit('mediaLibrary.contextSelection.deleteRequest');
    };

    $scope.restoreContextSelection = function () {
      $rootScope.$emit('mediaLibrary.contextSelection.restoreRequest');
    };

    $scope.emptyTrash = function () {
      $rootScope.$emit('mediaLibrary.trash.emptyRequest');
    };

    $scope.restoreTrash = function () {
      $rootScope.$emit('mediaLibrary.trash.restoreRequest');
    };

    $scope.joinSelection = function () {
      $rootScope.$emit('mediaLibrary.selection.joinRequest');
    };

    $scope.mediaLibraryShare = function (groups, users) {
      $rootScope.$broadcast('mediaLibrary.selection.shareRequest', groups, users);
    };

    $scope.toggleLocker = function () {
      $rootScope.$emit('mediaLibrary.context.toggleLockerRequest');
    };

    $scope.downloadArchiveSelection = function () {
      $rootScope.$broadcast('mediaLibrary.selection.downloadArchiveRequest');
    };
  });
