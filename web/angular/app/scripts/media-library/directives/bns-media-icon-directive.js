'use strict';

angular.module('bns.mediaLibrary.mediaIcon', [
  'bns.core.url',
  'bns.mediaLibrary.manager',
])

  /**
   * @ngdoc directive
   * @name bns.mediaLibrary.mediaPreview.bnsMediaIcon
   * @kind function
   *
   * @description
   * Displays the icon of a media element (document or folder)
   *
   * @returns {Object} The bnsMediaIcon directive
   */
  .directive('bnsMediaIcon', function (mediaLibraryManager) {
    return {
      scope: {
        item: '=media'
      },
      link: function (scope, element) {
        var typeClass = '';
        scope.$watch('item', function (item) {
          if (item) {
            element.removeClass(typeClass); // remove old class
            typeClass = 'bns-icon-' + mediaLibraryManager.getMediaType(item);
            element.addClass(typeClass);
          }
        });
      },
    };
  })

;
