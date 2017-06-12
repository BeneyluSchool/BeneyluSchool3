'use strict';

angular.module('bns.core.url', [])

  .constant('URL_BASE', '/ent/angular')

  .constant('URL_BASE_VIEW', '/ent/angular/app/views')

  .constant('URL_BASE_IMAGE', '/ent/angular/app/images')

  .constant('URL_BASE_WORKER', '/ent/angular/app/workers')

  .constant('URL_BASE_MEDIA', '/ent/medias')

  /**
   * @ngdoc service
   * @name bns.core.url.url
   * @kind function
   *
   * @description
   * URL factory
   *
   * ** Methods **
   * - `image(path)`: Generates an image url for the given path
   * - `view(path)`: Generates a view url for the given path
   * - `worker(path)`: Generates a Worker url for the given path
   * - `media(path)`: Generates a media (legacy) url for the given path
   *
   * @requires URL_BASE_IMAGE
   * @requires URL_BASE_VIEW
   *
   * @returns {Object} The url factory
   */
  .factory('url', function url (URL_BASE_IMAGE, URL_BASE_VIEW, URL_BASE_WORKER, URL_BASE_MEDIA) {
    return {
      image: image,
      view: view,
      worker: worker,
      media: media,
    };

    function image (path) {
      return URL_BASE_IMAGE + '/' + path;
    }

    function view (path) {
      return URL_BASE_VIEW + '/' + path;
    }

    function worker (path) {
      return URL_BASE_WORKER + '/' + path;
    }

    function media (path) {
      return URL_BASE_MEDIA + '/' + path;
    }
  })

;
