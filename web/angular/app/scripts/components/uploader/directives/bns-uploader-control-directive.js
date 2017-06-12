'use strict';

angular.module('bns.uploader.directive.control', [])

  /**
   * @ngdoc directive
   * @name  bns.uploader.directives.bnsUploaderControl
   * @kind function
   *
   * @description
   * Uploader control: handles file upload dialog, and visual status during
   * upload.
   *
   * @require $compile
   * @returns {Object} The bnsUploaderControl directive
   */
  .directive('bnsUploaderControl', function ($compile) {
    return {
      restrict: 'AE',
      link: link,
    };

    function link (scope, element, attrs) {
      var conf = scope.$eval(attrs.bnsUploaderControl) || {};

      // insert an hidden actual file input
      var uploadBtnId = 'upload-btn-'+Math.floor(Math.random()*100000);
      var $uploadField = $compile(angular.element('<input>').attr({
        type: 'file',
        'nv-file-select': true,
        uploader: 'uploader',
        multiple: conf.multiple || false,
        id: uploadBtnId,
        'class': 'uploader-hidden',
      }))(scope);
      element.after($uploadField);

      // trigger file dialog
      element.on('click', function (e) {
        e.preventDefault();
        // get a fresh handle on the upload btn, else click is not forwarded
        // correctly
        angular.element('#'+uploadBtnId).click();
      });

      scope.$watch('uploader.isUploading', function (isUploading) {
        if (isUploading) {
          element.addClass('uploading');
          element.attr('disabled', true);
        } else {
          element.removeClass('uploading');
          element.attr('disabled', false);
        }
      });
    }
  })

;
