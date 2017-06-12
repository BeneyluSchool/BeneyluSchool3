'use strict';

angular.module('bns.viewer.workshop.document.themeStyler', [
  'bns.viewer.workshop.document.widgetStyle',
])

  .factory('workshopThemeStyler', function (workshopWidgetStyle, stringHelpers, _) {

    /**
     * Adds context info to the given string, prepended by a @ character
     *
     * @param  {String} str
     * @param  {String} context
     * @return {String}
     */
    var contextualize = function (str, context) {
      if (context) {
        return str + '@' + context;
      }

      return str;
    };

    /**
     * Gets the uncontextualized version of the given str, ie the original
     * without context info (symbolized by a @ character)
     *
     * @param  {String} str
     * @return {String}     [description]
     */
    var uncontextualize = function (str) {
      var i = str.indexOf('@');
      if (i > -1) {
        return str.substr(0, i);
      }

      return str;
    };

    return {
      setTheme: function (theme) {
        this.theme = theme;
      },

      /**
       * Gets the theme styles corresponding to the given settings.
       * The result is a map of two keys:
       *   - css: contains a list of css properties with their values
       *   - classes: contains a list of css classes
       *
       * @param  {Object} settings The widget settings to parse
       * @param  {String} context An optional widget context
       * @return {Object}
       */
      getStylesForSettings: function (settings, context) {
        var srvc = this;

        if (!this.theme) {
          throw 'No theme set';
        }

        var styles = {
          css: {},
          classes: []
        };

        // get default style
        if (context) {
          styles.css = workshopWidgetStyle.getDefaultsForType(context);
        }

        _.forEach(settings, function (value, key) {

          if (context) {
            // try to get context-specific setting
            key = contextualize(key, context);
            if (!srvc.theme.options[key]) {

              // rollback to originial key
              key = uncontextualize(key);
              if (!srvc.theme.options[key]) {
                return;
              }
            }
          }

          var valueFound = _.find(srvc.theme.options[key].values, function (optionValue) {
            if ('string' === typeof optionValue) {
              return optionValue === value;
            } else if ('object' === typeof optionValue && optionValue) {
              return optionValue.value === value;
            }
          });

          if (valueFound) {
            if (valueFound['class']) {
              styles.classes.push(valueFound['class']);

              return;
            }

            if (valueFound.styles) {
              // styles defines a map { property: value }
              _.forEach(valueFound.styles, function (value, property) {
                styles.css[stringHelpers.snakeToDash(property)] = value;
              });
            } else {
              // style defines a single css value, so use the option key as css property
              styles.css[stringHelpers.snakeToDash(uncontextualize(key))] = valueFound.style || valueFound.value || valueFound;
            }
          }
        });

        return styles;
      },

      /**
       * Gets the theme options for the given key. Optionally if key is not
       * found, attempts to search for its uncontextualized variant.
       *
       * @param  {String} key
       * @param  {Boolean} bypassContext Defaults to false
       * @return {Object}
       */
      getOption: function (key, bypassContext) {
        // if key is not found but can be uncontextualized, use it
        if (!this.theme.options[key] && bypassContext) {
          key = uncontextualize(key);
        }

        // if key exists, return its option
        if (this.theme.options[key]) {
          return this.theme.options[key];
        }

        return null;
      }
    };
  });
