'use strict';

angular.module('bns.viewer.gdocViewer', [])

  .constant('gdocViewerBaseUrl', '/ent/medias/js/pdfjs/web/viewer.html')

  /**
   * @ngdoc service
   * @name bns.viewer.gdocViewer.gdocViewer
   * @kind function
   *
   * @description
   * The `gdocViewer` service is responsible for preparing URLs to be used in
   * embedded Google Docs viewers.
   *
   * ** Methods **
   * - `getUrl(resourceUrl)` : Builds and returns a valid, sce, URL to the gdoc
   *                           viewer.
   *
   * @return {Object}                   The gdocViewer service
   *
   * @requires gdocViewerBaseUrl
   * @requires $sce
   * @requires $location
   */
  .factory('gdocViewer', function (gdocViewerBaseUrl, $sce, $location) {

    /**
     * Gets a Strict Content Escaped Google Docs embed URL for the given
     * resource URL
     *
     * @param   {String} resourceUrl A resource URL
     * @returns {Object}             The SCE URL
     */
    var getUrl = function (resourceUrl) {
      return $sce.trustAsResourceUrl(getUnsecureUrl(resourceUrl));
    };

    /**
     * Gets the Google Docs embed url for the given resource URL.
     * That resource should of course be publicly accessible...
     *
     * @param   {String} resourceUrl The resource URL
     * @returns {String}             The gdoc embed URL
     */
    var getUnsecureUrl = function (resourceUrl) {
      // not an absolute url, need to craft it
      /*if (resourceUrl.indexOf($location.host() === -1)) {
        var root = '';
        if (resourceUrl.indexOf('/') !== 0) {
          root = '/';
        }
        resourceUrl = $location.protocol() + '://' + $location.host() + root + resourceUrl;
      }*/

      return gdocViewerBaseUrl + '?file=' + encodeURIComponent(resourceUrl) + '&embedded=true';
    };

    return {
      getUrl: getUrl
    };
  });
