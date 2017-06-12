'use strict';

angular.module('bns.mediaLibrary.scene', [
  'bns.mediaLibrary.scene.contentLoader',
  'bns.mediaLibrary.scene.contentDefault',
  'bns.mediaLibrary.scene.contentTrash',
  'bns.mediaLibrary.scene.contentGrouped',
  'bns.mediaLibrary.manager',
])

  /**
   * @ngdoc service
   * @name bns.mediaLibrary.scene.mediaLibrarySceneManager
   * @kind function
   *
   * @description
   * Media library scene manager, used by the different scene diplays.
   *
   * @requires collectionHelpers
   * @returns {Object} The mediaLibrarySceneManager service
   */
  .factory('mediaLibrarySceneManager', function (collectionHelpers, mediaLibraryManager) {

    /**
     * Gets the display type to use for the given context.
     *
     * @param {Object} context A scene context
     * @returns {String}
     */
    function getDisplayForContext (context) {
      if (!context) {
        return null;
      }

      if (mediaLibraryManager.isExternal(context)) {
        return 'grouped';
      }

      switch (context.slug) {
        case 'trash':
          return 'trash';
        case 'external':
          return 'grouped';
        default:
          return 'default';
      }
    }

    function aggregateProviders (medias) {
      return collectionHelpers.aggregate(medias, 'provider', 'provider.id');
    }

    return {
      getDisplayForContext: getDisplayForContext,
      aggregateProviders: aggregateProviders,
    };
  })

;
