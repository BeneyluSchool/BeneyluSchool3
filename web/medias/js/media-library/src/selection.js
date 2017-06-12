/* global document */
/* global window */
/* global Routing */

'use strict';

(function ($) {
  if (!$) {
    return console.warn('Cannot handle media library without jQuery');
  }

  /**
   * Closes the media library iframe. Should be called in the main window
   * context.
   */
  var closeMediaLibrary = function () {
    $('#media-library-iframe').fadeOut('fast', function () {
      // destroy the ng app
      var injector = this.contentWindow.$('[ng-app]').injector();
      if (injector) {
        injector.get('$rootScope').$destroy();
      }

      // remove the DOM
      $(this).remove();
    });
  };

  /**
   * Builds a call to the media library with the given config and executes the
   * given callback on successful selection.
   *
   * The config is used to specify number of items in the
   *
   * @param {Object} config The media library configuration
   * @param {Function} callback Callback to be executed on successful selection
   */
  var handleMediaLibraryCall = function (config, callback) {
    // call the media library
    $.ajax({
      url: Routing.generate('BNSAppMediaLibraryBundle_iframe', config),
      success: function(data) {
        $('body').prepend(data);

        window.mediaLibraryConfig = config;

        // wait for it to be loaded before attaching to its DOM
        $('iframe#media-library-iframe').on('load', function () {

          // store the config as a global var in the iframe window, angular will read it
          this.contentWindow.mediaLibraryConfig = config;

          // listen to events inside iframe with its local jQuery
          this.contentWindow.$('body').on('mediaLibrary.selection.done', function (e, data) {
            if ($.isFunction(callback)) {
              callback(data.selection);
            }

            closeMediaLibrary();
          });

          this.contentWindow.$('body').on('mediaLibrary.selection.abort', function () {
            closeMediaLibrary();
          });

          this.contentWindow.$('body').on('mediaLibrary.close', function () {
            closeMediaLibrary();
          });
        });
      }
    });
  };

  /**
   * Media insertion in editor, needs to be a global function
   */
  window.tinymce_button_media = function (ed) {
    var config = {
      mode: 'selection',
      submode: 'insert',
    };

    handleMediaLibraryCall(config, function (selection) {

      function insert (data) {
        ed.focus();
        ed.selection.setContent(data);
      }

      // async "recursion" to insert medias in the same order they have been
      // selected
      function processMediaForInsert (i) {
        if (i >= selection.length) {
          return;
        }

        // get template for current item
        var item = selection[i];
        $.ajax({
          url: Routing.generate('BNSAppMediaLibraryBundle_view', { type: 'insert', id: item.id }),
          type: 'POST',
          dataType: 'html',
          data: { id: item.id },
          success: function (data) {
            insert(data);
            // continue with next item
            processMediaForInsert(i + 1);
          }
        });
      }

      // start with first media
      processMediaForInsert(0);
    });
  };

  $(document).ready(function () {

    var $body = $('body');

    // selection of 1 document
    $body.on('click', '.media-selection', function (e) {
      e.preventDefault();
      var final_id = $(this).attr('data-final-id');
      var callback = $(this).attr('data-callback');
      var allowed_type = $(this).attr('data-allowed-type');

      var config = {
        mode: 'selection',
        max: 1,
        type: allowed_type || 'IMAGE'
      };

      // setup call to the media library
      handleMediaLibraryCall(config, function (selection) {
        // By default, selection is an array. Here we only consider the 1st element
        selection = selection[0];

        function success (data) {
          if ($('#' + final_id).length > 0) {
            $('.' + final_id).remove();
          }
          else {
            $('.' + final_id).attr('id', final_id);
          }

          $('#' + final_id)
            .val(selection.id)
            .trigger('input')
            .trigger('change')
          ;

          $('#' + callback).html(data);

          $('#cancel-' + final_id).removeClass('hide');
        }

        $.ajax({
          url: Routing.generate('BNSAppMediaLibraryBundle_view', { type: 'select', id: selection.id }),
          type: 'POST',
          dataType: 'html',
          data: { id: selection.id },
          success: success
        });
      });
    });

    // selection of multiple documents for attachment
    $body.on('click', '.media-join', function (e) {
      e.preventDefault();

      var reference = $(this).attr('data-reference') || $(this).parent().next('.resource-list').attr('id');

      var config = {
        mode: 'selection',
        submode: 'join',
      };

      // setup call to the media library
      handleMediaLibraryCall(config, function (selection) {
        function success (data) {
          $('#' + reference).prepend(data);
        }

        for (var k in selection) {
          var item = selection[k];

          $.ajax({
            url: Routing.generate('BNSAppMediaLibraryBundle_view', { type: 'join', id: item.id, editable: true }), // TODO
            type: 'POST',
            dataType: 'html',
            data: { id: item.id },
            success: success
          });
        }

      });
    });

    // view of a media
    $body.on('click', '.media-view', function (e) {
      e.preventDefault();

      var mediaId = $(this).data('media-id');

      if (!mediaId) {
        return;
      }

      var config = {
        mode: 'view',
        mediaId: mediaId,
      };

      var parent = $(this).parents('.resource-list,.bns-attachments').first();
      if (parent && parent.data('object-type')) {
        config.objectType = parent.data('object-type');
      }
      if (parent && parent.data('object-id')) {
        config.objectId = parent.data('object-id');
      }

      handleMediaLibraryCall(config);
    });
  });

})(window.jQuery);
