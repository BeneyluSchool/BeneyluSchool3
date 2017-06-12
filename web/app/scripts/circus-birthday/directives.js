(function (angular) {
'use strict';

angular.module('bns.circusBirthday.directives', [])

  .directive('bnsCircusBirthdayLevel', BNSCircusBirthdayLevelDirective)

;

function BNSCircusBirthdayLevelDirective () {

  return {
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var level = parseInt(attrs.level, 10) || 0;
    var labels = {
      1: 'Dessinateur débutant',
      2: 'Dessinateur confirmé',
      3: 'Véritable artiste',
    };
    var i = 0;
    while (i < level) {
      element.append('<div class="circus-birthday-level"></div>');
      i++;
    }

    element.append('<span class="md-body-2">'+labels[level]+'</span>');
  }

}

})(angular);
