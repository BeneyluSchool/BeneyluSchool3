'use strict';

angular.module('bns.uploader.directive', [])

  /**
   * @ngdoc directive
   * @name bns.uploader.directive.bnsUploader
   * @kind function
   *
   * @description
   * Main directive, sets up the uploader
   *
   * @require $injector
   * @require $parse
   * @require FileUploader
   * @require message
   * @require ApiCodes
   * @returns {Object} the bnsUploader directive
   */
  .directive('bnsUploader', function ($injector, $parse, FileUploader, message, ApiCodes) {
    return {
      restrict: 'A',
      compile: compile,
      scope: true,
      priority: 900,
    };

    function compile () {
      return {
        pre: function preCompile (scope, element, attrs) {
          var conf = angular.extend({}, $parse(attrs.bnsUploader)(scope));

          // TODO: inject vendor upload directives from here
          // element.append(angular.element('<div bns-uploader-control></div>'));

          // TODO: get the correct restangular instance from directive config
          var restangular = $injector.get('MediaLibraryRestangular');
          var baseUrl = restangular.configuration.baseUrl;

          scope.uploader = new FileUploader();
          scope.uploader.url = baseUrl + '/media';
          scope.uploader.autoUpload = true;

          var marker = conf.marker;

          scope.uploader.onAfterAddingFile = function (fileItem) {
            fileItem.url += '/' + marker + '/file';
          };

          scope.uploader.onSuccessItem = function () {
            message.success('UPLOADER.DOCUMENT_ADD_SUCCESS');
          };

          scope.uploader.onErrorItem = function (fileItem, response) {
            // reset upload state
            fileItem.isUploaded = false;

            if (response === ApiCodes.ERROR_NOT_ENOUGH_SPACE_USER) {
              message.error('MEDIA_LIBRARY.ERROR_NOT_ENOUGH_SPACE_USER');
            } else {
              message.error('UPLOADER.DOCUMENT_ADD_ERROR');
            }
          };
        }
      };
    }
  })
;
