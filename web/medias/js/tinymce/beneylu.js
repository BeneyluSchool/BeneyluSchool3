tinymce.PluginManager.add('beneylu', function (editor, url) {
  "use strict";

  editor.on('init', function() {
    $('.mce-floatpanel').remove();
    $('.mce-tooltip').remove();
    $("[aria-label='Font Sizes'] span").css('width','120px');
    $("[aria-label='Font Family'] span").css('width','140px');

    // AutosaveBundle stuff
    editor.on('keypress', function (e) {
      if(typeof(primaryKey) !== 'undefined') {
        checkSave();
      }
    });
    editor.on('setContent', function (e) {
      if(typeof(primaryKey) !== 'undefined') {
        checkSave();
      }
    });
  });

});
