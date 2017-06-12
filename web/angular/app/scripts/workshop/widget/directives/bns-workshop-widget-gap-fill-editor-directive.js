'use strict';
angular.module('bns.workshop.gapFillTextEditor', [
  'angular-uuid'
])

  .directive('bnsWorkshopGapFillTextEditor', BNSWorkshopGapFillTextEditorDirective)

;
function BNSWorkshopGapFillTextEditorDirective ($compile, $translate, uuid) {
  return {
    terminal: true,
    link: postLink
  };

  function postLink (scope, element) {
    scope.config = getTinymceConfig();
    element.attr('bns-tinymce', 'config');
    element.removeAttr('bns-workshop-gap-fill-text-editor');
    $compile(element)(scope);
  }

  function getTinymceConfig () {
    var style = '[data-bns-gap-guid]{cursor:pointer;color:#388E3C}';
    return {
      content_style: style,
      toolbar1: 'gap',
      menubar: false,
      statusbar: false,
      formats: {
        gap:{
          inline: 'span',
          attributes: {
            'data-bns-gap-guid': '%guid'
          }
        }
      },
      setup: function (editor) {
        editor.on('gap:remove', function() {
          var selectedNode = editor.selection.getNode();
          var guid = selectedNode.attributes['data-bns-gap-guid'].value;
          editor.formatter.remove('gap', {guid: guid});
        });
        editor.on('gap:add', function() {
          var guid = uuid.v4();
          editor.formatter.apply('gap', {
            guid: guid
          });
        });
        editor.addButton('gap', {
          image: '../../angular/app/images/workshop/widget-option/gap-fill.png',
          tooltip: $translate.instant('WORKSHOP.QUESTIONNAIRE.ADD_GAP_TOOLTIP'),
          onpostrender: function () {
            var btn = this;
            editor.on('init', function () {
              // highlight button when selection changes
              editor.selection.selectorChanged('[data-bns-gap-guid]', function (state) {
                btn.active(state);
              });
            });
          },
          onclick: function (event, data) {
            var originalSelection = editor.selection.getBookmark();
            editor.selection.moveToBookmark(originalSelection);
            var selectedNode = editor.selection.getNode();
            if (selectedNode.hasAttribute('data-bns-gap-guid')) {
              editor.fire('gap:remove', data);
            } else {
              editor.fire('gap:add', data);
            }
            editor.fire('Change');
          },
          classes: 'bns-add-gap-btn'
        });
      }
    }
  }
}


