'use strict';

angular.module('bns.core', [
  // runs
  'bns.core.runs.stateError',

  'bns.core.debounce',
  'bns.core.downloader',
  'bns.core.message',
  'bns.core.modal',
  'bns.core.bindValue',
  'bns.core.focusRequest',
  'bns.core.stringHelpers',
  'bns.core.objectHelpers',
  'bns.core.collectionHelpers',
  'bns.core.trustHtml',
  'bns.core.nl2br',
  'bns.core.octet',
  'bns.core.timer',
  'bns.core.restangularInit',
  'bns.core.dragdrop',
  'bns.core.navigationTree',
  'bns.core.apiCodes',
  'restangular',
  'ui.router',
  'ui.sortable',
  'sun.scrollable',
  'dndLists',
  'ngDragDrop',
  'angular-loading-bar',
  'btford.modal',
  'duScroll',
  'ngSanitize',
  'ngAnimate'
])

  .factory('_', function ($window) {
    return $window._;
  })

  .factory('io', function ($window) {
    return $window.io;
  })

  .factory('jQuery', function ($window) {
    return $window.jQuery;
  })

  .factory('CKEDITOR', function ($window) {
    return $window.CKEDITOR;
  })

  .factory('tinymce', function ($window) {
    return $window.tinymce;
  })

  .run(function ($rootScope, $state, $stateParams, jQuery) {
    $rootScope.$state = $state;
    $rootScope.$stateParams = $stateParams;

    // for drag/drop events compatibility, link native attribute to jQuery event
    jQuery.event.props.push('dataTransfer');
  })

  .config(function (cfpLoadingBarProvider) {
    // automatic loading bar only, no spinner
    cfpLoadingBarProvider.includeSpinner = false;
  })
;
