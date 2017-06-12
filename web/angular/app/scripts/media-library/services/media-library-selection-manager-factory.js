'use strict';

angular.module('bns.mediaLibrary.selectionManager', [
    'bns.mediaLibrary.manager',
    'bns.core.treeUtils',
  ])

  /**
   * @ngdoc service
   * @name bns.mediaLibrary.selectionManager.mediaLibrarySelectionManager
   * @kind function
   *
   * @description
   * This manager handles selection formatting for API dialogs, and various
   * batch tasks.
   *
   * @requires mediaLibraryManager
   *
   * @returns {Object} The mediaLibrarySelectionManager
   */
  .factory('mediaLibrarySelectionManager', function (mediaLibraryManager, treeUtils) {

    /**
     * Checks that the given item (file or folder) is manageable.
     *
     * @param {Object} item
     * @returns {Boolean}
     */
    function isManageable (item) {
      return !!item.manageable;
    }

    var manager = {};

    manager.getFormattedData = function (selection) {
      var data = [];

      angular.forEach(selection, function (item) {
        var itemData = {};

        if (mediaLibraryManager.isFile(item)) {
          itemData.TYPE = 'MEDIA';
        } else if (mediaLibraryManager.isUserFolder(item)) {
          itemData.TYPE = 'MEDIA_FOLDER_USER';
        } else if (mediaLibraryManager.isGroupFolder(item)) {
          itemData.TYPE = 'MEDIA_FOLDER_GROUP';
        } else {
          console.warn('Unknown item in selection', item);
          return;
        }

        itemData.ID = item.id;

        data.push(itemData);
      });

      return data;
    };

    /**
     * Favorite every element in the given selection
     *
     * @param {CollectionMap} selection
     */
    manager.favorite = function (selection) {
      selection.batch(function (item) {
        if (isManageable(item)) {
          item.is_favorite = true;
        }
      });
    };

    /**
     * Unfavorite every element in the given selection
     *
     * @param {CollectionMap} selection
     */
    manager.unfavorite = function (selection) {
      selection.batch(function (item) {
        if (isManageable(item)) {
          item.is_favorite = false;
        }
      });
    };

    /**
     * Privatize every element in the given selection
     *
     * @param {Array} selection A collection of Files and Folders
     */
    manager.privatize = function (selection) {
      manager.cascadeSet(selection, 'is_private', true, true, isManageable);
    };

    /**
     * Publicize every element in the given selection
     *
     * @param {Array} selection A collection of Files and Folders
     */
    manager.publicize = function (selection) {
      manager.cascadeSet(selection, 'is_private', false, true, isManageable);
    };

    /**
     * Cascade-sets the given property to the given value, in the given
     * collection. Optionally affects medias of folders.
     * Optionally, specify a test function, that receives each item in the
     * collection and returns whether that item should be affected.
     *
     * @param {Array} selection A File and Folder collection
     * @param {string} property
     * @param value
     * @param {Boolean} doMedias Whether to also affect medias
     * @param {Function} testFn A test function.
     */
    manager.cascadeSet = function (selection, property, value, doMedias, testFn) {
      treeUtils.walk(selection, function (item) {
        if ((testFn && testFn(item)) || !testFn) {
          item[property] = value;
        }

        if (doMedias && mediaLibraryManager.isFolder(item)) {
          angular.forEach(item.medias, function (media) {
            if ((testFn && testFn(item)) || !testFn) {
              media[property] = value;
            }
          });
        }
      });
    };

    return manager;
  });
