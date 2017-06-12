(function (angular) {
'use strict';

angular.module('bns.main.attachments.attachments', [
  'bns.core.octet',
  'bns.core.global',
])

  .directive('bnsAttachments', BNSAttachmentsDirective)
  .controller('BNSAttachments', BNSAttachmentsController)

;

/**
 * @ngdoc directive
 * @name bnsAttachments
 * @module bns.main.attachments.attachments
 *
 * @description
 * Displays the given list of media attachments.
 *
 * ** Attributes **
 *  - `source` {Array} : the source collection of medias
 *  - `editable` {Boolean} : whether medias can be added or removed. If true, an
 *                           hidden input is added to each media, for
 *                           compatibility with actual forms.
 *  - `startOpen` {Boolean} : whether medias are visible by default. Defaults to
 *                            true.
 */
function BNSAttachmentsDirective () {

  return {
    restrict: 'EA',
    replace: true,
    scope: {
      source: '=',
      editable: '=',
      startOpen: '=?',
    },
    templateUrl: 'views/main/attachments/bns-attachments.html',
    controller: 'BNSAttachments',
    controllerAs: 'attachments',
    bindToController: true,
  };

}

function BNSAttachmentsController (_, $scope, $element, global) {

  var attachments = this;
  attachments.remove = remove;
  attachments.active = false;
  attachments.anonymous = global('anonymous');

  init();

  function init () {
    // start open by default
    if (attachments.startOpen !== false) {
      attachments.startOpen = true;
    }

    angular.element('body').on('mediaLibrary.selection.done', handleMediaLibrarySelection);

    $scope.$on('$destroy', function cleanup () {
      angular.element('body').off('mediaLibrary.selection.done', handleMediaLibrarySelection);
    });

    // mark current component as active upon media-library invocation
    $element.on('click', '.media-join', function () {
      attachments.active = true;
    });

    function handleMediaLibrarySelection (event, data) {
      // do not handle this (global) event if current component is not concerned
      if (!(attachments.editable && attachments.active)) {
        return;
      }
      $scope.$apply(function () {
        updateAttachments(data && data.selection);
        attachments.active = false;
      });
    }
  }

  function remove (media) {
    if (!attachments.source) {
      return console.warn('Cannot remove attachment without source');
    }
    var idx = attachments.source.indexOf(media);
    if (idx > -1) {
      attachments.source.splice(idx, 1);
    }
  }

  function updateAttachments (collection) {
    if (!attachments.source) {
      return console.warn('Cannot update attachments without source');
    }
    if (collection && collection.length) {
      // remove duplicates in source and selection
      var res = _.uniq(_.union(attachments.source, collection), 'id');
      // update source with the new values
      attachments.source.splice(0, attachments.source.length);
      Array.prototype.push.apply(attachments.source, res);
    } else {
      attachments.source.splice(0, attachments.source.length);
    }
  }

}

})(angular);
