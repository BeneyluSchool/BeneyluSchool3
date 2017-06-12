'use strict';

angular.module('bns.viewer.service', [
  'bns.viewer.controller',
  'bns.core.url',
])

  /**
   * @ngdoc service
   * @name bnsViewer
   * @kind function
   *
   * @description
   * The bnsViewer factory service allows to display BNS resources in a viewer.
   * It is a two-step process (see example below). The viewer is appended to the
   * DOM, and has a dedicated scope and controller.
   *
   * ** Parameters **
   * `config`: A configuration object. Supported keys are:
   * - `templateUrl`: {String} An alternate URL to load the viewer template
   *                  from.
   * - `controller`: {String|Object} An alternate controller to use.
   * - `container`: The DOM element to which the viewer will be appended.
   *                Defaults to the document body.
   *
   * The returned object has the following methods:
   * - `activate(locals)`: Activates the viewer (and make it visible). The given
   *                       local variables are passed to its attached scope.
   *                       ** Mandatory local variables **
   *                       `media|mediaId`: a media instance, or its ID
   *                       ** Optional local variables **
   *                       `noClose`: if truey, disables the close button
   * - `deactivate()`: Deactivates the viewer, remove it from the DOM and
   *                   destroy its scope.
   *
   * @example
   * // 1) create a new instance
   * var viewer = bnsViewer(myConfiguration);
   *
   * // 2) give it a media to display
   * viewer.activate({ media: myAlreadyLoadedMedia });
   *
   * // 2b) alternatively, pass only the media id
   * viewer.activate({ mediaId: myMediaId });
   *
   * @requires $http
   * @requires $templateCache
   * @requires $document
   * @requires $animate
   * @requires $rootScope
   * @requires $compile
   * @requires $controller
   *
   * @returns {Object} The bnsViewer factory function
   */
  .factory('bnsViewer', function ($http, $templateCache, $document, $animate, $rootScope, $compile, $controller, url) {
    return function bnsViewerFactory (config) {
      config = config || {};
      var templateUrl = config.templateUrl || url.view('viewer/viewer.html'),
        controller =    config.controller || 'ViewerController',
        controllerAs =  config.controllerAs || 'ctrl',
        container =     config.container || angular.element('body'),
        element,
        scope,
        html,
        viewer = {};

      html = $http.get(templateUrl, {
        cache: $templateCache
      })
        .then(function (response) {
          return response.data;
        });

      var activate = function (locals) {
        html.then(function (html) {
          if (!element) {
            attach(html, locals);
          }
        });
      };

      var deactivate = function () {
        return $animate.leave(element).then(function() {
          scope.$destroy();
          element = null;
        });
      };

      var attach = function (html, locals) {
        element = angular.element(html);

        // insert into the DOM
        $animate.enter(element, container);

        // prepare a new, dedicated scope
        scope = $rootScope.$new();

        // pass local variables to scope
        if (locals) {
          for (var name in locals) {
            scope[name] = locals[name];
          }
        }

        // add a dedicated controller
        var ctrl = $controller(controller, { $scope: scope, viewer: viewer });
        scope[controllerAs] = ctrl;

        // boot the whole thing
        $compile(element)(scope);
      };

      // ----- Public API -----

      viewer.activate = activate;
      viewer.deactivate = deactivate;

      return viewer;
    };
  })
;
