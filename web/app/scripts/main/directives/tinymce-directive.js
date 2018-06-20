(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.main.tinymce
 */
angular.module('bns.main.tinymce', [
  'restangular',
  'ui.tinymce',
  'bns.core.loader',
])

  .directive('bnsTinymce', BNSTinymceDirective)

;

/**
 * @ngdoc directive
 * @name bnsTinymce
 * @module bns.main.tinymce
 *
 * @description
 * Wrapper for the ui-tinymce directive, that uses configuration from the API
 * and lazy-loads the editor.
 *
 * @requires $compile
 * @requires $window
 * @requires Restangular
 * @requires Loader
 */
function BNSTinymceDirective ($compile, $window, $timeout, $mdMedia, Restangular, Loader, _) {

  return {
    restrict: 'A',
    scope: true,
    link: postLink,
    terminal: true,
    priority: 1050,
  };

  function postLink (scope, element, attrs) {
    Restangular.one('wysiwyg/configuration').withHttpConfig({cache: true}).get()
      .then(function success (response) {
        setupTinymce(response);
      })
    ;

    function setupTinymce (configuration) {
      // no editor present, delay conf and load it (url is given by conf)
      if (!$window.tinymce) {
        var loader = new Loader();

        return loader.require([
          configuration.editor_script,
        ], function editorLoaded () {
          // wrap in timeout to avoid race conditions when multiple editors are
          // loaded simultaneously
          $timeout(function () {
            setupTinymce(configuration);
          });
        });
      }

      // override configuration
      if (attrs.bnsTinymce) {
        var local = scope.$eval(attrs.bnsTinymce);
        var override = angular.copy(local);
        // start from an empty object to avoid polluting the default conf
        if (configuration.toolbar1 && override.toolbar1 &&
          (
            configuration.toolbar1.contains('correction') ||
            (configuration.toolbar2 && configuration.toolbar2.contains('correction'))
          )) {
          override.toolbar1 = override.toolbar1.concat(' | correction');
        }
        configuration = angular.extend(local, configuration, override);
      }

      //Mobile config
      if ($mdMedia.hasTouch && !$mdMedia('gt-sm')) {
        var mobileConfig = {
          plugins: [
            'lists', 'autolink'
          ],
          toolbar1: 'fullscreen | undo redo | bold italic underline | media',
          menu : {
            insert : null,
            format: null,
            table: null,
            tools: null
          }
        };
        // hook into editor setup for custom code
        mobileConfig.setup = function (editor) {
          // quick fix to trigger fullscreen
          editor.on('focus', function () {
            if (!angular.element('.mce-fullscreen').length) {
              editor.execCommand('mceFullScreen');
            }
          });

          editor.on('FullscreenStateChanged', function(e) {
            if(e.state) { //enter fullscreen
              editor.execCommand('mceFocus');
            } else {
              angular.element('iframe[id^=ui-tinymce-]').blur();
            }
          });
        };

        //Merge mobile config
        _.merge(configuration, mobileConfig);
      }

      // expose the configuration, and add the actual editor directive
      scope.tinymceConfig = configuration;

      element.removeAttr('bns-tinymce');
      element.attr('ui-tinymce', attrs.bnsTinymce || 'tinymceConfig');

      // add a simple model, required by ui-tinymce
      if (!attrs.ngModel) {
        scope.model = element.val();
        element.attr('ng-model', 'model');
      }

      $compile(element)(scope);
    }
  }

}

})(angular);
