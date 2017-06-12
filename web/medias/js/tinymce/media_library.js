tinymce.PluginManager.add('media_library', function (editor, url) {
  "use strict";
  editor.addButton('media', {
    tooltip: 'Media library',
    image: tinymce.baseURL + '/../../medias/images/media-library/tiny_icon.png',
    onclick: function () {
      // use our custom function set in the bundle
      window.tinymce_button_media && window.tinymce_button_media(editor);
    },
  })
});
