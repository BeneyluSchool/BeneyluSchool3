(function (angular) {
'use strict';

angular.module('bns.main.correction', [
  'angular-uuid',
])

  .constant('BNS_ANNOTATION_TYPES', [
    { type: 'NOUN', color: '#64B5F6', },     // blue-300
    { type: 'VERB', color: '#81C784', },     // green-300
    { type: 'HOMOPHONIC', color: '#EF6C00', },  // orange-800
    { type: 'VOCABULARY', color: '#FFFF00', },  // yellow-A200
    { type: 'PUNCTUATION', color: '#9575CD', }, // deep-purple-300
    { type: 'NONE', color: '#90A4AE', },        // color-blue-grey-300
  ])

;

})(angular);
