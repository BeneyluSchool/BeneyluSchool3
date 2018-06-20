(function (angular) {
'use strict';

angular.module('bns.main.correction')

  .directive('bnsCorrectionLink', BnsCorrectionLinkDirective)
  .controller('BnsCorrectionLink', BnsCorrectionLinkController)

;

/**
 * @ngdoc directive
 * @name bnsCorrectionLink
 * @module bns.main.correction
 *
 * @description
 * The actual correction directive that works together with tinymce to provide
 * correction capabilities.
 *
 * **Attributes**
 *  - `bnsCorrectionLink` {=Object}: The Correction model to bind to.
 *  - `bnsEditable` {=Boolean}: Whether to allow edition, addition and removal
 *                              of correction data.
 *  - `bnsCorrectionConfig` {=Object}: The tinymce config to enhance.
 */
function BnsCorrectionLinkDirective () {

  return {
    scope: {
      model: '=ngModel',
      correction: '=bnsCorrectionLink',
      config: '=bnsCorrectionConfig',
      editable: '=bnsEditable',
    },
    require: ['uiTinymce'],
    controller: 'BnsCorrectionLink',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BnsCorrectionLinkController (_, $scope, $attrs, $rootScope, $translate, uuid, dialog, BNS_ANNOTATION_TYPES) {

  var ANNOTATION_NAMES = _.map(BNS_ANNOTATION_TYPES, 'type');

  var ctrl = this;

  init();

  function init () {
    ctrl.config = angular.extend(ctrl.config, getTinymceConfig());

    $scope.$on('annotation:add', addAnnotation);

    var unlistenRemoveAnnotation = $rootScope.$on('annotation:remove', removeAnnotation);

    $scope.$on('$destroy', function cleanup () {
      unlistenRemoveAnnotation();
    });

    var stopInitAnnotations = $scope.$watch('ctrl.correction.correction_annotations', function (annotations) {
      // wait for annotations to be available
      if (!annotations) {
        return;
      }
      stopInitAnnotations();

      // make sure they are sorted before applying other watch
      annotations.sort(function (a, b) {
        return a.sortable_rank - b.sortable_rank;
      });
      $scope.$watchCollection('ctrl.correction.correction_annotations', syncSortableRanks);
    });

  }

  function addAnnotation (event, data) {
    if (!ctrl.correction) {
      ctrl.correction = {};
    }
    if (!angular.isArray(ctrl.correction.correction_annotations)) {
      ctrl.correction.correction_annotations = [];
    }

    // update existing annotation or create a new one
    var annotation;
    var existing = _.find(ctrl.correction.correction_annotations, { guid: data.guid });
    if (existing) {
      annotation = existing;
    } else {
      annotation = {
        comment: '',
        attachments: [],
      };
      ctrl.correction.correction_annotations.push(annotation);
    }
    angular.extend(annotation, {
      guid: data.guid,
      type: data.type,
      label: data.label,
    });
  }

  function removeAnnotation (event, data) {
    ctrl.editor.fire('annotation:remove', data);
  }

  function syncSortableRanks () {
    if (!(ctrl.correction && ctrl.correction.correction_annotations)) {
      return;
    }

    var i = 1;
    angular.forEach(ctrl.correction.correction_annotations, function (annotation) {
      annotation.sortable_rank = i;
      i++;
    });
  }

  function getTinymceConfig () {
    var style = '[data-bns-annotation]{cursor:pointer;border: 1px solid rgba(0,0,0,.12);margin:-1px}';
    angular.forEach(BNS_ANNOTATION_TYPES, function (annotation) {
      style += '[data-bns-annotation="'+annotation.type+'"]{background-color:'+annotation.color+'}';
    });

    var toolbar = '';
    if (ctrl.editable) {
      toolbar = 'annotation' + ANNOTATION_NAMES.join(' annotation');
    } else {
      if (ctrl.correction) {
        toolbar = 'verifyCorrection';
      }
    }

    return {
      content_style: style,
      formats: {
        annotation:{
          inline: 'span',
          attributes: {
            'data-bns-annotation': '%type',
            'data-bns-annotation-guid': '%guid',
          }
        }
      },
      setup: function (editor) {
        ctrl.editor = editor;
        editor.on('click', onClick);

        if (toolbar) {
          for (var i = 1; i < 5; i++) {
            if (!editor.settings['toolbar'+i]) {
              if (ctrl.editable) {
                // add our custom toolbar in the first available slot
                editor.settings['toolbar'+i] = toolbar;
              } else {
                // append our custom toolbar to the end of the last existing
                editor.settings['toolbar'+(i-1)] += ' | ' + toolbar;
              }
              break;
            }
          }
        }

        if (ctrl.editable) {
          editor.on('annotation:remove', onAnnotationRemove);

          angular.forEach(ANNOTATION_NAMES, function (type) {
            editor.addButton('annotation'+type, {
              text: $translate.instant('ANNOTATIONS.TYPE_' + type),
              // tooltip: type,
              onclick: function () {
                applyAnnotation(type);
              },
              classes: 'bns-annotation-btn bns-annotation-btn-'+type,
              onpostrender: function () {
                var btn = this;
                editor.on('init', function () {
                  // highlight button when selection changes
                  editor.selection.selectorChanged('[data-bns-annotation="'+type+'"]', function (state) {
                    btn.active(state);
                  });
                });
              },
            });
          });
        } else {
          editor.addButton('verifyCorrection', {
            text: $translate.instant('ANNOTATIONS.BUTTON_VERIFY_CORRECTION'),
            onclick: function () {
              verifyCorrection();
            },
            classes: 'bns-annotation-btn bns-annotation-btn-verify',
          });
        }

        // editor.on('init', function () {
        //   // TODO: purify annotations
        //   console.log('init', editor.getContent());
        // });

        function onClick (event) {
          if (!(event.target.hasAttribute('data-bns-annotation') && event.target.hasAttribute('data-bns-annotation-guid'))) {
            $scope.$emit('annotation:unfocus');
            if (!$rootScope.$$phase) {
              $scope.$apply();
            }
            return;
          }

          $scope.$emit('annotation:focus', {
            guid: event.target.attributes['data-bns-annotation-guid'].value,
          });
        }

        function onAnnotationRemove (event) {
          var currentSelection = editor.selection.getBookmark();
          var annotationMarkups = editor.dom.select('[data-bns-annotation-guid="'+event.annotation.guid+'"]');
          angular.forEach(annotationMarkups, function(annotationMarkup){
            editor.selection.select(annotationMarkup);
            editor.formatter.remove('annotation', {type: event.annotation.type, guid: event.annotation.guid });
            editor.selection.moveToBookmark(currentSelection);
          });

          // Tell tinyMCE to update model
          ctrl.editor.fire('Change');
        }

        function applyAnnotation (type) {
          var guid = uuid.v4();
          var originalSelection = editor.selection.getBookmark();
          editor.selection.moveToBookmark(originalSelection); // restore selection directly after setting a bookmark, else it is messed up
          var isCollapsed = editor.selection.isCollapsed();
          var selectedNode = editor.selection.getNode();
          var currentAnnotation;
          if (selectedNode.hasAttribute('data-bns-annotation')) {
            currentAnnotation = selectedNode;
          }

          // update existing annotation
          if (currentAnnotation && isCollapsed) {
            editor.selection.select(currentAnnotation);
            guid = currentAnnotation.getAttribute('data-bns-annotation-guid');
            // if trying to apply same annotation type, change to NONE
            if (type === currentAnnotation.getAttribute('data-bns-annotation')) {
              type = 'NONE';
            }
          }

          editor.formatter.apply('annotation', {
            type: type,
            guid: guid,
          });
          editor.fire('Change');

          // get content of whatever the resulting annotation is
          // editor.selection.select(editor.dom.select('[data-bns-annotation-guid="'+guid+'"]')[0]);
          var resultingAnnotation = editor.dom.select('[data-bns-annotation-guid="'+guid+'"]')[0];
          var text = resultingAnnotation ? resultingAnnotation.textContent : '';

          editor.selection.moveToBookmark(originalSelection);

          // force collapse selection, to allow seamless update of annotation
          // when reusing annotation buttons without changing the selection.
          editor.selection.collapse();

          $scope.$emit('annotation:add', {
            guid: guid,
            type: type,
            label: text,
          });
          if (!$rootScope.$$phase) {
            $scope.$apply();
          }
        }
      }
    };
  }

  function verifyCorrection () {
    if (!ctrl.correction) {
      return;
    }

    return dialog.show({
      templateUrl: 'views/main/correction/verify-correction-dialog.html',
      locals: {
        current: ctrl.model,
        previous: ctrl.correction.last_correction,
      },
    });
  }

}

})(angular);
