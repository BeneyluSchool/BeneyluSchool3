/**
 * Created by Edwin on 03/11/2015.
 */
//TODO split this file

var translations;
var code = tinymce.i18n.getCode();

switch (code) {
  case 'fr_FR':
    translations = {
      "Change mode" : "Changer de mode",
      "Media library" : "M\u00e9diath\u00e8que",
    };
    break;
  case 'es':
    translations = {
      "Change mode" : "Cambiar de modo",
      "Media library" : "Mediateca",
    };
    break;
  default:
    translations = {
      "Change mode" : "Change mode",
      "Media library" : "Media library",
    };
    break;
}

tinymce.addI18n(code, translations);
