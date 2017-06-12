'use strict';

angular.module('bns.mediaLibrary.manager', [
  'bns.mediaLibrary.mediaManager',
  'bns.core.treeUtils',
])

  .factory('mediaLibraryManager', function ($translate, treeUtils, _, mediaManager) {

    // inherit base mediaManager
    var manager = angular.copy(mediaManager);

    manager.canUpload = function (folder) {
      return manager.canManage(folder);
    };

    manager.canCreate = function (folder) {
      return manager.canManage(folder) &&
        (manager.isUserFolder(folder) ? manager.hasAvailableSpace(folder) : true)
      ;
    };

    manager.canRead = function (folder) {
      return !!folder.readable;
    };

    manager.canReadContent = function (folder) {
      if (manager.isLockerFolder(folder)) {
        return manager.canWrite(folder);
      }

      return manager.canRead(folder);
    };

    manager.canWrite = function (folder) {
      return !!folder.writable;
    };

    manager.canManage = function (folder) {
      return !!folder.manageable;
    };

    manager.canToggleLocker = function (folder) {
      return manager.canWrite(folder) && manager.isGroupFolder(folder);
    };

    manager.hasAvailableSpace = function (folder) {
      return folder.usage ? (folder.usage.total - folder.usage.current > 0) : true;
    };

    manager.setupSpecialFolders = function (library) {
      var favorites = {
        id: '_favorites_',
        slug: 'favorites',
        role: 'favorites',
        unique_key: 'favorites',
        medias: [],
      };

      // favorites.label = $translate.instant('MEDIA_LIBRARY.FAVORITES');
      $translate('MEDIA_LIBRARY.MY_FAVORITES').then(function (translation) {
        favorites.label = translation;
      });

      // populate favorites
      manager.symlinkAllElements(library, library.favorites, favorites);
      library.favorites_folder = favorites;

      var recents = {
        id: '_recents_',
        slug: 'recents',
        role: 'recents',
        unique_key: 'recents',
        medias: library.recents,
      };

      $translate('MEDIA_LIBRARY.RECENT_DOCUMENTS').then(function (translation) {
        recents.label = translation;
      });

      manager.symlinkAllElements(library, library.recents, recents);
      library.recents_folder = recents;

      var trash = {
        id: '_trash_',
        slug: 'trash',
        role: 'trash',
        unique_key: 'trash',
        medias: library.garbage.MEDIAS,
        children: library.garbage.MEDIA_FOLDERS,
      };

      $translate('MEDIA_LIBRARY.MY_TRASH').then(function (translation) {
        trash.label = translation;
      });
      library.trash_folder = trash;

      var external = {
        id: '_external_',
        slug: 'external',
        role: 'external',
        unique_key: 'external',
        medias: library.external || [],
        hide_empty: true,
      };

      $translate('MEDIA_LIBRARY.EXTERNAL_RESOURCES').then(function (translation) {
        external.label = translation;
      });
      library.external_folder = external;

      library.special_folders = [
        library.favorites_folder,
        library.recents_folder,
        library.trash_folder,
      ];
    };

    /**
     * Parses the library, looking for folders and medias contained it the given
     * src. If found, adds a reference to them in the given dest folder.
     *
     * @param {Obejct} library
     * @param {Object} src Folder containing elements to symlink
     * @param {Object} dest Folder receiving the links
     */
    manager.symlinkAllElements = function (library, src, dest) {
      var folders = src.children || src.MEDIA_FOLDERS;
      var medias = src.medias || src.MEDIAS;

      // if array given, consider a list of medias
      // TODO: remove this when API is corrected
      if (angular.isArray(src)) {
        medias = src;
      }

      dest.children = [];
      dest.medias = [];

      angular.forEach(folders, function (subfolder) {
        // find the same folder in the library, and link to it
        var actualFolder = manager.findFolder(library, subfolder);
        if (actualFolder) {
          dest.children.push(actualFolder);
        }
      });
      angular.forEach(medias, function (media) {
        // find the same media in the library, and link to it
        var actualMedia = manager.findMedia(library, media);
        if (actualMedia) {
          dest.medias.push(actualMedia);
        }
      });
    };

    /**
     * Parses the library and tries to find a media equal to the given one.
     *
     * @param {Object} library
     * @param {Object} obj The reference media
     * @returns {Object} The equal media, if found
     */
    manager.findMedia = function (library, obj) {
      // create the media holder 1 scope above the finder
      var media;

      // finder function, invoked on each folder
      var compFn = function (item) {
        // update reference in the parent scope
        media = _.find(item.medias, { id: obj.id });

        // media found, so return true to stop walking the tree
        return !!media;
      };

      // The comparison function will be invoked for each folder, scanning its
      // medias. If found, the reference here will be updated with the media
      treeUtils.find(library.my_folder, compFn);
      if (!media) {
        treeUtils.find(library.group_folders, compFn);
      }

      return media;
    };

    /**
     * Parses the library and tries to find a folder equal to the given one.
     *
     * @param {Object} library
     * @param {Object} ref The reference folder
     * @returns {Object} The folder, if found
     */
    manager.findFolder = function (library, ref) {
      var folder;
      var compFn = function (item) {
        return (ref.id === item.id) && (ref.type === item.type);
      };

      folder = treeUtils.find(library.my_folder, compFn);
      if (!folder) {
        folder = treeUtils.find(library.group_folders, compFn);
      }

      return folder;
    };

    return manager;
  });
