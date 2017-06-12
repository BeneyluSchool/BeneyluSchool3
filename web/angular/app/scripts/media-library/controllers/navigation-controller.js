'use strict';

angular.module('bns.mediaLibrary')

  .controller('MediaLibraryNavigationCtrl', function ($scope, $rootScope, starterKit, NavigationTree) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.shared.userTree = new NavigationTree(null, 'unique_key');
      $scope.shared.groupTree = new NavigationTree(null, 'unique_key');
      $scope.shared.specialTree = new NavigationTree(null, 'unique_key');
      $scope.hasStarterKit = false !== starterKit;

      $scope.root = null;

      ctrl.mapFolders();
    };

    ctrl.mapFolders = function () {
      var library = $scope.shared.library;

      if (!library) {
        $rootScope.$emit('mediaLibrary.needed');
        return;
      }

      ctrl.mapUserFolders(library);
      ctrl.mapGroupFolders(library);
      ctrl.mapSpecialFolders(library);
    };

    ctrl.mapUserFolders = function (library) {
      if (!library.my_folder) {
        return;
      }

      $scope.shared.userTree.setData([
        library.my_folder,
      ]);
    };

    ctrl.mapGroupFolders = function (library) {
      $scope.shared.groupTree.setData(library.group_folders);
    };

    ctrl.mapSpecialFolders = function (library) {
      $scope.shared.specialTree.setData(library.special_folders);
    };

    ctrl.refreshTrees = function () {
      $scope.shared.userTree.refreshParents();
      $scope.shared.groupTree.refreshParents();
    };

    ctrl.refreshContextRoot = function () {
      var root = $scope.shared.userTree.getRoot($scope.shared.context);
      if (!root) {
        root = $scope.shared.groupTree.getRoot($scope.shared.context);
      }

      if (root) {
        $scope.root = root;
      } else {
        $scope.root = null;
      }
    };

    ctrl.init();

    /**
     * Checks whether then given root node should be displayed
     *
     * @param {Object} root
     * @returns {Boolean}
     */
    $scope.canDisplayRoot = function (root) {
      // nodes that have not explicitly asked to be hidden when empty are always shown
      if (!root.hide_empty) {
        return true;
      }

      // display root only if it has children, or medias
      return (root.children && root.children.length) || (root.medias && root.medias.length);
    };

    // local setup when global object is loaded
    $scope.$on('mediaLibrary.loaded', function () {
      ctrl.mapFolders();
    });

    $scope.$on('mediaLibrary.folders.changed', function () {
      ctrl.refreshTrees();
    });

    var unregisterContextChanged = $rootScope.$on('mediaLibrary.context.changed', function (e, model) {
      $scope.shared.userTree.expandAncestors(model);
      $scope.shared.groupTree.expandAncestors(model);
      var parent = $scope.shared.userTree.getParent(model);
      if (!parent) {
        parent = $scope.shared.groupTree.getParent(model);
      }
      $scope.shared.parent = parent;

      ctrl.refreshContextRoot();
    });

    $scope.$on('$destroy', function () {
      unregisterContextChanged();
    });
  });
