'use strict';

angular.module('bns.mediaLibrary.dev', [])

  .factory('MediaLibraryDev', function () {

    var removeMedias = function (folder) {
      delete folder.medias;
      for (var child in folder.children) {
        removeMedias(folder.children[child]);
      }
    };

    var makeGroupFolder = function (folder, suffix, idBase) {
      folder.label += suffix;
      folder.id += idBase;
      folder.slug = (folder.slug + suffix).toLowerCase().replace(' ', '-');
      folder.unique_key = folder.unique_key.replace('user', 'group') + suffix.replace(' ', '');
      folder.manageable = false;

      for (var child in folder.children) {
        makeGroupFolder(folder.children[child], suffix, idBase);
      }

      return folder;
    };

    var prepareLibrary = function (library) {
      library.group_folders.push(makeGroupFolder(angular.copy(library.my_folder), ' 1', 1000));
      library.group_folders.push(makeGroupFolder(angular.copy(library.my_folder), ' 2', 2000));
    };

    return {
      prepareLibrary: prepareLibrary,
    };
  });
