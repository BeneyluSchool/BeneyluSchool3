tinymce.PluginManager.add('changemode', function (editor, url) {
  "use strict";

  editor.addButton('changemode', {
    tooltip: 'Change mode',
    icon: 'changemode',
    onclick: function () {
      toggleButtons();
    },
  });

  editor.on('init', function() {
    if (typeof (getCookie('tinymce_mode')) != 'undefined') {
      if (getCookie('tinymce_mode') == "simple") {
        toggleButtons();
      }
    }
  });

  function toggleButtons () {
    var boutons=[ 'alignleft', 'alignright', 'bullist', 'alignjustify', 'aligncenter', 'numlist', 'outdent', 'indent', 'link'];

    for (var i=0; i<boutons.length;i++){
      $('.mce-i-'+boutons[i]).parent().parent().toggle();
    }

    $('.mce-menubar').toggle();
    $("[aria-label='Font Family']").toggle();

    setTinyCookie($('.mce-i-indent').parent().parent().css('display')=='none');
  }

  function setTinyCookie (isSimple) {
    if(isSimple){
      document.cookie='tinymce_mode=simple;path=/';
    } else {
      document.cookie='tinymce_mode=advanced;path=/';
    }
  }

  function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for (var i=0; i<ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0)==' ') c = c.substring(1);
      if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
    }
    return "";
  }
});
