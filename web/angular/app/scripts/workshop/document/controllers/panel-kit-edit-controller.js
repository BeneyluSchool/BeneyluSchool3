'use strict';

angular.module('bns.workshop.document.panelKitEditController', [
  'ui.router',
  'bns.core.url',
  'bns.workshop.document.widgetGroupEditor',
  'bns.workshop.document.wysiwygTheme',
  'bns.workshop.document.state',
  'bns.workshop.document.manager',
  'bns.viewer.workshop.document.themeStyler',
  'bns.core.message'
])

  .controller('WorkshopDocumentPanelKitEditController', function (widgetGroup, $timeout, $q, $rootScope, $state, $scope, url, widgetGroupEditor, wysiwygTheme, WorkshopDocumentState, workshopThemeStyler, workshopDocumentManager, uuid) {

    var ctrl = this;
    ctrl.document = WorkshopDocumentState.document;
    ctrl.widgetGroup = widgetGroup;
    ctrl.currentWidget = widgetGroup._embedded.widgets[0];
    ctrl.getThemeOption = getThemeOption;
    ctrl.getFormTemplate = getFormTemplate;
    ctrl.submit = submit;
    ctrl.cancel = cancel;
    ctrl.draftMessage = '';
    ctrl.getWysiwygConfiguration = getWysiwygConfiguration;
    WorkshopDocumentState.dirty = false;
    ctrl.advancedSettingsTemplate = '/ent/angular/app/views/workshop/widget/panel/advanced-settings.html';
    ctrl.toggleSetting = toggleSetting;
    ctrl.gapFillText = false;

    init();


    function init () {
      angular.forEach(widgetGroup._embedded.widgets, function (widget) {

        // init model with actual empty values, for easy clean rollback
        if ('media' === widget.type) {
          if (!widget.media_id) {
            widget.media_id = null;
          }
          if (!widget._embedded) {
            widget._embedded = {
              media: null,
            };
          }
        }
        if ('multiple' == widget.type) {
          questionInit(widget.type);

          $scope.toggle = function (item, list) {
            var idx = list.indexOf(item);
            if (idx > -1) {
              list.splice(idx, 1);
            }
            else {
              list.push(item);
            }
          };

          $scope.exists = function (item, list) {
            return list.indexOf(item) > -1;
          };
        }

        if ('simple' == widget.type) {
          questionInit(widget.type);
        }

        if ('closed' == widget.type) {
          questionInit(widget.type);
        }

        if ('gap-fill-text' == widget.type) {
          questionInit(widget.type);
          ctrl.gapFillText = true;
        }

        function questionInit(type) {

          if (widget._embedded && widget._embedded.extended_settings && widget._embedded.extended_settings.correct_answers) {
            widget.choices = widget._embedded.extended_settings.choices;
            widget.correct = widget._embedded.extended_settings.correct_answers;
          } else {
            if (type != 'closed') {
              widget.choices = [];
              widget.correct = [];
              var max = 2;
              if (type == 'simple') {
                max = 3;
                widget.correct = 1;
              }
              for(var i = 0 ; i < max; i++) {
                widget.choices.push({
                  label: ''
                })
              }
            }

            if (type == 'closed') {
              widget.correct = '';
            }

            if (type == 'gap-fill-text') {
              widget.choices = '';
              widget.correct = [];
            }
          }

          if (widget._embedded.extended_settings && widget._embedded.extended_settings.advanced_settings) {
            widget.advancedSettings =  widget._embedded.extended_settings.advanced_settings;
          } else {
            widget.advancedSettings = {};
            widget.advancedSettings.show_chrono = false;
            widget.advancedSettings.chrono = {
              minutes: 1,
              seconds: 0
            };
            widget.advancedSettings.hide_solution = false;
            widget.advancedSettings.show_comment = false;
            widget.advancedSettings.comment = '';
            widget.advancedSettings.show_clue = false;
            widget.advancedSettings.clue = '';
            widget.advancedSettings.show_help = false;
            widget.advancedSettings.help = '';
            widget.advancedSettings.type = 'text';
          }

          $scope.addAnswer = function () {
            widget.choices.push({
              label: ''
            });
          };

          $scope.addCorrect = function () {
            widget.correct.push({
              label: ''
            })
          };

          $scope.deleteAnswer = function (index) {
            if (typeof  widget.correct == 'object' && widget.correct.length > 0) {
              var idx = widget.correct.indexOf(index + 1);
              if (idx > -1) {
                widget.correct.splice(idx, 1);
              }
            } else {
              if (widget.correct === index + 1) {
                widget.correct = [];
              }
            }
            if (widget.choices) {
              widget.choices.splice(index, 1);
            }
          };

          $scope.changeType = function (type) {
            if (widget.advancedSettings.type != type) {
              widget.advancedSettings.type = type;
              widget.choices = [];
              widget.correct = [];
              var max = 2;
              if (widget.type == 'simple') {
                max = 3;
                widget.correct = 1;
              }
              if (type == 'text') {
                for(var i = 0 ; i < max; i++) {
                  widget.choices.push({
                    label: ''
                  })
                }
              } else {
                for(var i = 0 ; i < max; i++) {
                  widget.choices.push({
                    media_id: ''
                  })
                }
              }
            }
          };

          ctrl.config = ctrl.getWysiwygConfiguration(widget);
          ctrl.config.toolbar1 = ctrl.config.toolbar1 + '| media';
        }
      });

      widgetGroupEditor.init(ctrl.widgetGroup);
      widgetGroupEditor.autosave(2, function (promise) {
        ctrl.draftMessage = 'WORKSHOP.DOCUMENT.DRAFT_SAVING';
        promise.then(success).finally(end);

        function success () {
          ctrl.draftMessage = 'WORKSHOP.DOCUMENT.DRAFT_SAVED';
        }
        function end () {
          $timeout(function () {
            ctrl.draftMessage = '';
          }, 5000);
        }
      });



      // prevent state change if form has changed
      $scope.$on('$stateChangeStart', function (evt) {
        if (WorkshopDocumentState.dirty && !WorkshopDocumentState.ignoreStateConstraints) {
          evt.preventDefault();
        }
      });

      $scope.$watch('widgetGroupForm.$dirty', function (isDirty) {
        if (isDirty) {
          WorkshopDocumentState.dirty = true;
        }
      });

      // listen to option changes
      $scope.$on('widget.option.changed', function (event, widget, property, value) {
        // initialize the widget's settings if necessary
        if (!widget.settings) {
          widget.settings = {};
        }

        // assign, or remove, the new property value
        if (value) {
          widget.settings[property] = value;
        } else {
          delete widget.settings[property];
        }

        WorkshopDocumentState.dirty = true;
      });

      $scope.$on('widget.media.changed', function (event, widget, property, id, media) {
        // naively remove the id suffix to get embedded resource property name
        var objectProperty = property.replace('_id', '');
        if (!widget._embedded) {
          widget._embedded = {};
        }

        if (id) {
          widget[property] = id;
          widget._embedded[objectProperty] = media;
        } else {
          widget[property] = null; // force property to be present, to support PATCH
          widget._embedded[objectProperty] = null;
        }
      });

      var unwatchRootEvent = $rootScope.$on('workshop.document.widgetGroup.deleted', function (evt, item) {
        if (item.id === ctrl.widgetGroup.id) {
          gotoParentState();
        }
      });

      $scope.$on('$destroy', function () {
        unwatchRootEvent();
      });
    }

    /**
     * Submits all changes to the given WidgetGroup
     */
    function submit() {

      if (ctrl.gapFillText) {
        preSave();
      }
      widgetGroupEditor.commit().then(function () {
        gotoParentState();
      });
    }

    /**
     * Cancels all changes to the current WidgetGroup
     */
    function cancel () {
      widgetGroupEditor.rollback().then(function () {
        if ('full' === ctrl.widgetGroup.type) {
          var widget = ctrl.widgetGroup._embedded.widgets[0];
          if ('media' === widget.type && !widget.media_id) {
            // after rollback, widget has no media: delete it
            workshopDocumentManager.removeWidgetGroup(ctrl.widgetGroup);
          }
        }
        gotoParentState();
      });
    }

    function getWysiwygConfiguration (widget) {
      return wysiwygTheme.getConfiguration(widget);
    }

    /**
     * Gets the theme option for the given key
     *
     * @param {String} key
     * @returns {Object}
     */
    function getThemeOption (key) {
      // get the actual option, or its uncontextualized version
      return workshopThemeStyler.getOption(key, true);
    }

    /**
     * Gets the form template url of the given widget
     *
     * @returns {String}
     */
    function getFormTemplate (widget) {
      // template name is simply the widget's type
      var template = widget.type;

      // add subtype if necessary
      if (widget.subtype) {
        template += '-' + widget.subtype;
      } else if (widget._embedded && widget._embedded.media) {
        // check if we have a template dedicated to this media type
        var mediaType = widget._embedded.media.type_unique_name.toLowerCase();
        if (['image'].indexOf(mediaType) > -1) {
          template += '-' + mediaType;
        }
      }

      return url.view('workshop/widget/panel/' + template + '.html');
    }

    function gotoParentState() {
      $scope.widgetGroupForm.$setPristine();
      WorkshopDocumentState.dirty = false;
      $state.go('^');
    }

    function refreshExpander() {
      $timeout(function(){
        $scope.$broadcast('track.height');
      },100, true);
    }

    function toggleSetting() {
      refreshExpander();
    }

    function preSave () {
      var correctAnswers = [];
      var guids = [];
      angular.forEach(widgetGroup._embedded.widgets, function (widget) {
        if (widget.choices) {
          var text =  angular.element('<div>' + widget.choices + '</div>');
          angular.forEach(text.find('span[data-bns-gap-guid]'), function(gap) {
            gap = angular.element(gap);
            var guid = gap.attr('data-bns-gap-guid');

            if(guids.indexOf(guid) !== -1) {
              guid = uuid.v4();
              gap.attr('data-bns-gap-guid', guid);
            }

            guids.push(guid);
            var label = gap.text();
            correctAnswers.push({
              guid : guid,
              label : label
            });
          });
          widget.correct = correctAnswers;
          widget.choices = text.html();
        } else {
          widget.choices = '';
        }
      });
    }

  });
