'use strict';

angular.module('bns.core')

  .directive('bnsWysiwygCommand', function (CKEDITOR) {

    return function (scope, element, attrs) {
      var command = attrs.bnsWysiwygCommand;
      if (!command) {
        return;
      }

      var onStateChange = function () {
        // apply a class on the element based on its command state
        switch (this.state) {
          case CKEDITOR.TRISTATE_ON:
            element.addClass('active');
            break;
          case CKEDITOR.TRISTATE_OFF:
            element.removeClass('active');
            element.removeClass('disabled');
            break;
          case CKEDITOR.TRISTATE_DISABLED:
            element.addClass('disabled');
            break;
        }
      };

      var onInstanceReady = function (event) {
        registerCommandState(command, event.editor);
      };

      // helper to register command state change event listener
      var registerCommandState = function (command, editor) {
        // @todo: unregister on scope destroy
        editor.getCommand(command).on('state', onStateChange);
      };

      // register command state changes on each editor created form now on
      CKEDITOR.on('instanceReady', onInstanceReady);

      // also register for editors already exising
      for (var editorName in CKEDITOR.instances) {
        registerCommandState(command, CKEDITOR.instances[editorName]);
      }

      element.on('click', function () {
        if (!CKEDITOR.currentInstance) {
          return;
        }

        // execute the command in the current editor
        CKEDITOR.currentInstance.execCommand(command);
      });

      scope.$on('$destroy', function () {
        // remove listener for new editors
        CKEDITOR.removeListener('instanceReady', onInstanceReady);
        // remove listener for the command change on existing editors
        for (var editorName in CKEDITOR.instances) {
          CKEDITOR.instances[editorName].getCommand(command).removeListener('state', onStateChange);
        }
      });
    };
  });
