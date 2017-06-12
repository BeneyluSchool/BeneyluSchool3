'use strict';

angular.module('bns.core.downloader', [])

  /**
   * @ngdoc service
   * @name bns.core.downloader
   * @kind function
   *
   * @description
   * Downloads files over AJAX
   *
   * @requires $window
   * @requires $http
   */
  .factory('downloader', function ($window, $http) {

    // default file name used
    var DEFAULT_FILE_NAME = 'download.bin';

    // default mime type used
    var OCTET_STREAM_MIME = 'application/octet-stream';

    // whether to log debug info
    var DEBUG = false;

    return {
      get: get
    };

    /**
     * Issues a GET request to the given URL, that results in a file download
     * File name can be given by the X-Filename response header.
     *
     * @param {String}   url             URL where to GET
     * @param {Object}   params          GET parameters
     * @param {Function} successCallback Function to be executed on success,
     *                                   receives the response as parameter.
     * @param {Function} errorCallback   Function to be executed on error,
     *                                   receives the response as parameter.
     */
    function get (url, params, successCallback, errorCallback) {
      $http.get(url, {
        params: params,
        responseType: 'arraybuffer'
      })
        .success(function success (data, status, headers) {
          var isSuccess = false;

          // Get the headers
          headers = headers();

          // Get the filename from the x-filename header
          var filename = headers['x-filename'] || DEFAULT_FILE_NAME;

          // Determine the content type from the header or default to "application/octet-stream"
          var contentType = headers['content-type'] || OCTET_STREAM_MIME;

          var blob;

          try {
            // Try using msSaveBlob if supported
            log('Trying saveBlob method ...');
            blob = new $window.Blob([data], { type: contentType });
            if ($window.navigator.msSaveBlob) {
              $window.navigator.msSaveBlob(blob, filename);
            } else {
              // Try using other saveBlob implementations, if available
              var saveBlob = $window.navigator.webkitSaveBlob || $window.navigator.mozSaveBlob || $window.navigator.saveBlob;
              if (saveBlob === undefined) {
                throw 'Not supported';
              }
              saveBlob(blob, filename);
            }
            log('saveBlob succeeded');
            isSuccess = true;
          } catch (e) {
            log('saveBlob method failed with the following exception:');
            log(e);
          }

          if (!isSuccess) {
            // Get the blob url creator
            var urlCreator = $window.URL || $window.webkitURL || $window.mozURL || $window.msURL;
            if (urlCreator) {
              // Try to use a download link
              var link = $window.document.createElement('a');
              if('download' in link) {
                // Try to simulate a click
                try {
                  // Prepare a blob URL
                  log('Trying download link method with simulated click ...');
                  blob = new $window.Blob([data], { type: contentType });
                  var url = urlCreator.createObjectURL(blob);
                  link.setAttribute('href', url);

                  // Set the download attribute (Supported in Chrome 14+ / Firefox 20+)
                  link.setAttribute('download', filename);

                  // Simulate clicking the download link
                  var event = $window.document.createEvent('MouseEvents');
                  event.initMouseEvent('click', true, true, window, 1, 0, 0, 0, 0, false, false, false, false, 0, null);
                  link.dispatchEvent(event);
                  log('Download link method with simulated click succeeded');
                  isSuccess = true;
                } catch (e) {
                  log('Download link method with simulated click failed with the following exception:');
                  log(e);
                }
              }

              if (!success) {
                // Fallback to window.location method
                try {
                  // Prepare a blob URL
                  // Use application/octet-stream when using window.location to force download
                  log('Trying download link method with window.location ...');
                  blob = new $window.Blob([data], { type: OCTET_STREAM_MIME });
                  var downloadUrl = urlCreator.createObjectURL(blob);
                  $window.location = downloadUrl;
                  log('Download link method with window.location succeeded');
                  isSuccess = true;
                } catch (e) {
                  log('Download link method with window.location failed with the following exception:');
                  log(e);
                }
              }
            }
          }

          if (!isSuccess) {
            // Fallback to window.open method
            log('No methods worked for saving the arraybuffer, using last resort window.open');
            $window.open(url, '_blank', '');
          }

          if (isSuccess && angular.isFunction(successCallback)) {
            successCallback(data);
          }

        })
        .error(function error (data, status) {
          log('Download request failed with status: ' + status);
          if (angular.isFunction(errorCallback)) {
            errorCallback(data, status);
          }
        })
      ;
    }

    function log () {
      if (DEBUG) {
        for (var i = 0; i < arguments.length; i++) {
          console.log(arguments[i]);
        }
      }
    }
  });
