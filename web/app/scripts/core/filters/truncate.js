(function (angular) {
'use strict';

angular.module('bns.core.characters', [])

  .filter('characters', CharactersFilter)

;

function CharactersFilter () {

  return function (str, chars, hardBreak) {
    if (!isFinite(chars)) {
      return str;
    }
    if (str && str.length > chars) {
      if (hardBreak) {
        // hard break: keep the requested number of chars
        str = str.substring(0, chars);
      } else {
        // soft break: on last space in interval
        str = str.substring(0, chars + 1);
        var lastSpace = str.lastIndexOf(' ');
        if (lastSpace > -1) {
          // there's at least one space (can be the last character): break on it
          str = str.substr(0, lastSpace);
        } else {
          // no space found: break after requested number of chars
          str = str.substring(0, chars);
        }
      }

      str += '…';
    }

    return str;
  };

}

})(angular);
