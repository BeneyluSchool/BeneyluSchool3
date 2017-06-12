'use strict';

/**
 * @ngdoc module
 * @name  bns.uploader
 *
 * @description
 * # BNS Uploader
 *
 * A standalone uploader, supporting file drop and auto-upload
 */
angular.module('bns.uploader', [
  // module components
  'bns.uploader.directive',
  'bns.uploader.directive.control',

  // core dependencies
  'bns.core.message',
  'bns.core.apiCodes',
  'bns.core.translationInit',
  'bns.core.restangularInit',
  'bns.mediaLibrary.restangular', // TODO: move it in a core module

  // vendor libs
  'angularFileUpload',
]);
