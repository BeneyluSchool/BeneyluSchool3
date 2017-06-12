'use strict';

angular.module('bns.viewer.embeddedVideo', [])

  .value('embeddedVideoBaseUrls', {
    'youtube': 'https://www.youtube.com/embed',
    'dailymotion': 'https://www.dailymotion.com/embed/video',
    'vimeo': 'https://player.vimeo.com/video',
  })

  /**
   * @ngdoc service
   * @name bns.viewer.embeddedVideo.embeddedVideo
   * @kind function
   *
   * @description
   * The `embeddedVideo` service is responsible for preparing URLs to be used in
   * embedded video players.
   *
   * ** Methods **
   * - `getYoutubeUrl(code)` : Builds and returns a valid, sce, URL to the
   *                           YouTube player.
   * - `getDailymotionUrl(code)` : Builds and returns a valid, sce, URL to the
   *                               Dailymotion player.
   * - `getVimeoUrl(code)` : Builds and returns a valid, sce, URL to the Vimeo
   *                         player.
   *
   * @return {Object}                   The embeddedVideo service
   *
   * @requires embeddedVideoBaseUrls
   * @requires $sce
   */
  .factory('embeddedVideo', function (embeddedVideoBaseUrls, $sce) {

    /**
     * Gets a Strict Content Escaped YouTube embed URL for the given code.
     *
     * @param   {String} code A video code
     * @returns {Object}      The SCE URL
     */
    var getYoutubeUrl = function (code) {
      return $sce.trustAsResourceUrl(embeddedVideoBaseUrls.youtube + '/' + code);
    };

    /**
     * Gets a Strict Content Escaped Dailymotion embed URL for the given code.
     *
     * @param   {String} code A video code
     * @returns {Object}      The SCE URL
     */
    var getDailymotionUrl = function (code) {
      return $sce.trustAsResourceUrl(embeddedVideoBaseUrls.dailymotion + '/' + code);
    };

    /**
     * Gets a Strict Content Escaped Vimeo embed URL for the given code.
     *
     * @param   {String} code A video code
     * @returns {Object}      The SCE URL
     */
    var getVimeoUrl = function (code) {
      return $sce.trustAsResourceUrl(embeddedVideoBaseUrls.vimeo + '/' + code);
    };

    return {
      getYoutubeUrl: getYoutubeUrl,
      getDailymotionUrl: getDailymotionUrl,
      getVimeoUrl: getVimeoUrl,
    };
  });
