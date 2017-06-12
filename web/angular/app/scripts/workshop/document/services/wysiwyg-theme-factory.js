'use strict';

angular.module('bns.workshop.document.wysiwygTheme', [
  'bns.core.url',
  'bns.core.stringHelpers',
  'bns.viewer.workshop.document.themeStyler',
  'bns.core.tokenize'
])

  /**
   * @ngdoc service
   * @name bns.workshop.document.wysiwygTheme.wysiwygTheme
   * @kind function
   *
   * @description
   * Bridge between workshop theme options and the actual wysiwyg editor used
   * by the app (tinymce).
   *
   * ** Methods **
   * - `getConfiguration(widget)`: gets a valid configuration for the widget
   *
   * @requires $translate
   * @requires URL_BASE
   * @requires stringHelpers
   * @requires workshopThemeStyler
   *
   * @returns {Object} The wysiwygTheme service
   */
  .factory('wysiwygTheme', function ($translate, URL_BASE, stringHelpers, workshopThemeStyler, tokenizeFilter) {
    var service = {
      types: {
        title: ['font_family', 'font_size', 'color'],
        text: ['font_family', 'font_size', 'color'],
        textbox: ['font_family', 'font_size', 'color'],
        multiple: ['font_family', 'font_size', 'color'],
        simple: ['font_family', 'font_size', 'color'],
        closed: ['font_family', 'font_size', 'color'],
        'gap-fill-text': ['font_family', 'font_size', 'color']
      },
      getConfiguration: getConfiguration,
    };

    return service;


    /* ---------------------------------------------------------------------- *\
     *    API
    \* ---------------------------------------------------------------------- */

    /**
     * Gets a complete wysiwyg configuration, tailored for the given widget.
     *
     * @param {Object} widget A workshop Widget
     * @returns {Object} A valid tinymce configuration
     */
    function getConfiguration (widget) {
      if (!service.types[widget.type]) {
        throw 'Unknown widget type "' + widget.type + '"';
      }

      var configuration = {};

      // defaults settings
      addDefaults(configuration);

      // widget-specific settings
      angular.forEach(service.types[widget.type], function (themeOptionName) {
        var option = workshopThemeStyler.getOption(themeOptionName + '@' + widget.type, true);
        if ('font_family' === themeOptionName) {
          addFontFamilies(option, configuration);
        } else if ('font_size' === themeOptionName) {
          addFontSizes(option, configuration);
        } else if ('color' === themeOptionName) {
          addColors(option, configuration);
        }
      });

      return configuration;
    }


    /* ---------------------------------------------------------------------- *\
     *    Internals
    \* ---------------------------------------------------------------------- */

    /**
     * Adds default values to the given wysiwyg configuration.
     *
     * @param {Object} configuration
     */
    function addDefaults (configuration) {
      // hide 'file', 'edit', ... menu
      configuration.menubar = false;

      // hide the bottom bar (dom info + resize)
      configuration.statusbar = false;

      // two-rows toolbar
      configuration.toolbar1 = 'styleselect';
      configuration.toolbar2 = 'bold italic underline | alignleft aligncenter alignright alignjustify';

      // no plugins loaded
      configuration.plugins = '';

      configuration.height = 300;

      // hook into editor setup for custom code
      configuration.setup = function (editor) {
        // quick fix to trigger ng-model refresh upon textcolor change, because
        // the plugin only triggers 'NodeChange' event
        editor.on('change', function () {
          editor.fire('ExecCommand');
        });
      };
    }

    /**
     * Adds all required configuration to have the given color theme option
     * available in the wysiwyg.
     *
     * @param {Object} option A Theme option
     * @param {Object} configuration
     */
    function addColors (option, configuration) {
      // parse theme colors and add them to the map
      var colorMap = [];
      angular.forEach(option.values, function (value) {
        if (angular.isString(value)) {
          colorMap.push(value.replace('#', ''));
          colorMap.push(value);
        } else {
          colorMap.push((value.style || value.value).replace('#', ''));
          colorMap.push(value.label);
        }
      });

      // setup color picker, load plugin, add toolbar button
      configuration.textcolor_cols = 5;
      configuration.textcolor_rows = 8;
      configuration.textcolor_map  = colorMap;
      configuration.plugins += ' textcolor';
      configuration.toolbar2 += ' | forecolor';
    }

    /**
     * Adds all required configuration to have the given font-size theme option
     * available in the wysiwyg.
     *
     * @param {Object} option A theme option
     * @param {Object} configuration
     */
    function addFontSizes (option, configuration) {
      // parse theme font sizes and add them to a collection
      var formats = [];
      angular.forEach(option.values, function (value) {
        var format = {
          title: $translate.instant('WORKSHOP.LABEL_FRONT_SIZE_'+tokenizeFilter(value.code)) || value.label || value.value,
          selector: 'div,p,h1,h2,h3,h4,h5,h6',
          styles: {},
        };

        if (value.styles) {
          // styles defines a map { property: value }
          angular.forEach(value.styles, function (value, property) {
            format.styles[stringHelpers.snakeToCamel(property)] = value;
          });
        } else {
          // style defines a single css value, so use the option key as css property
          format.styles.fontSize = value.style || value.value || value;
        }
        formats.push(format);
      });

      // add sizes as custom formats, and preserve default formats
      // if (!configuration.style_formats) {
      //   configuration.style_formats = [];
      // }
      // configuration.style_formats.push({title: 'Taille', items: formats});
      // configuration.style_formats_merge = true;
      configuration.style_formats = formats;
    }

    /**
     * Adds all required configuration to have the given font-family theme
     * option available in the wysiwyg.
     *
     * @param {Object} option A theme option
     * @param {Object} configuration
     */
    function addFontFamilies (option, configuration) {
      var formats = [];
      angular.forEach(option.values, function (value) {
        formats.push((value.label || value.value) + '=' + (value.style || value.value));
      });

      configuration.font_formats = formats.join(';');
      configuration.toolbar1 += ' | fontselect ';
    }
  })

;
