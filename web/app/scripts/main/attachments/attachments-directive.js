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
 *  - `showControl` {Boolean} : whether to show the "join documents" button.
 *                              Defaults to true.
 *  - `showMedias` {Boolean} : whether to show the media list. Defaults to true.
 *  - `type` {mediatype} : type of the medias to be selected.
 *  - `bnsLabel` {String} : Custom label to use inside the attachments button.
 *  - `bnsTitle` {String} : Custom title to use above the attachments.
 *  - `bnsMax` {Integer}: The maximum number of attachments to allow. Defaults
 *                        to undefined => unlimited.
 *
 * ** Templates **
 *  - `bns-attachment-details` : Custom template to use for displaying details
 *                               of an attachment (below its label). Has access
 *                               to the `attachment` scope variable
 *  - `bns-attachment-link` : Custom template to use for displaying the link to
 *                            an attachment. Has access to the `attachment`
 *                            scope variable.
 */
function BNSAttachmentsDirective () {

  return {
    restrict: 'EA',
    replace: true,
    scope: {
      source: '=',
      editable: '=',
      startOpen: '=?',
      showControl: '=?',
      showMedias: '=?',
      compact: '=?',
      formName: '@?',
      type: '=bnsType',
      label: '@bnsLabel',
      title: '@bnsTitle',
      removeTooltip: '@bnsRemoveTooltip',
      max: '=bnsMax',
      drag: '@bnsDraggable'
    },
    templateUrl: function ($element, $attrs) {
      $attrs.$bnsAttachmentsTemplateElement = $element.clone();
      return 'views/main/attachments/bns-attachments.html';
    },
    controller: 'BNSAttachments',
    controllerAs: 'attachments',
    bindToController: true,
  };

}

function BNSAttachmentsController (_, $scope, $element, $attrs, global) {

  var attachments = this;
  attachments.remove = remove;
  attachments.active = false;
  attachments.anonymous = global('anonymous');
  attachments.formName = $scope.formName || 'resource-joined[]';
  attachments.canDrag = canDrag;

  init();

  function init () {
    parseAdditionalTemplates();

    // start open by default
    if (attachments.startOpen !== false) {
      attachments.startOpen = true;
    }

    // show control by default
    if (attachments.showControl !== false) {
      attachments.showControl = true;
    }

    // show medias by default
    if (attachments.showMedias !== false) {
      attachments.showMedias = true;
    }

    angular.element('body').on('mediaLibrary.selection.done', handleMediaLibrarySelection);
    angular.element('body').on('mediaLibrary.selection.abort', handleMediaLibraryAbort);

    $scope.$on('$destroy', function cleanup () {
      angular.element('body').off('mediaLibrary.selection.done', handleMediaLibrarySelection);
      angular.element('body').off('mediaLibrary.selection.abort', handleMediaLibraryAbort);
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

    function handleMediaLibraryAbort () {
      $scope.$apply(function () {
        attachments.active = false;
      });
    }
  }

  function canDrag() {
    if(attachments.drag === 'true') {
      return {sort: true};
    } else {
      return {sort: false};
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

  function parseAdditionalTemplates () {
    // Grab the user template from attr (set by directive template)
    attachments.attachmentDetailsTemplate = getTemplateByQuery($attrs.$bnsAttachmentsTemplateElement, 'bns-attachment-details');
    attachments.attachmentLinkTemplate = getTemplateByQuery($attrs.$bnsAttachmentsTemplateElement, 'bns-attachment-link');

    function getTemplateByQuery (sourceElement, query) {
      var element = sourceElement[0].querySelector(query);

      return element && element.outerHTML;
    }
  }

}

})(angular);
