'use strict';

angular.module('bns.workshop.document.widgetGroupEditor', [
  'bns.core.modelEditor',
  'bns.workshop.document.manager',
])

  .factory('widgetGroupEditor', function widgetGroupEditor (ModelEditor, workshopDocumentManager) {
    var service = new ModelEditor();

    service.doCommit = function (widgetGroup) {
      return workshopDocumentManager.editWidgetGroup(
        widgetGroup,
        getFormData(widgetGroup)
      );
    };

    service.doAutosave = function (widgetGroup) {
      return workshopDocumentManager.draftWidgetGroup(
        widgetGroup,
        getFormData(widgetGroup)
      );
    };

    return service;


    /* ---------------------------------------------------------------------- *\
     *    Internals
    \* ---------------------------------------------------------------------- */

    /**
     * Builds API-compliant data from the given WidgetGroup
     *
     * @param {Object} widgetGroup
     * @returns {Object}
     */
    function getFormData (widgetGroup) {
      var data = {
        id: widgetGroup.id,
        workshop_widgets: [],
      };

      angular.forEach(widgetGroup._embedded.widgets, function (widget) {
        data.workshop_widgets.push({
          content: widget.content,
          settings: widget.settings,
          media_id: widget.media_id,
          workshop_widget_extended_setting: {
            choices: widget.choices,
            correct_answers: widget.correct,
            advanced_settings: widget.advancedSettings
          }
        });
      });

      return data;
    }
  })

;
