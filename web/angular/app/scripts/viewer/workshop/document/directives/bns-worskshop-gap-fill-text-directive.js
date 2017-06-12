'use strict';
angular.module('bns.workshop.gapFillText', [])

  .directive('bnsWorkshopGapFillText', BNSWorkshopGapFillText)

;
function BNSWorkshopGapFillText ($compile, $translate) {
    return {
        scope: {
            text: '=',
            response: '=',
            answers: '=',
            canAnswer: '='
        },
        link: postLink
    };

    function postLink (scope, element) {
        var source = angular.element(scope.text);

        angular.forEach(source.find('span[data-bns-gap-guid]'), function(gap, idx) {
            gap = angular.element(gap);
            var id = gap.attr('data-bns-gap-guid');
            var template = '<md-input-container class="gap-fill-input" md-no-float><input ng-class="{\'good-answer\': answers && (answers.indexOf(\''+id+'\') !== -1), \'wrong-answer\': answers && (!answers.indexOf(\''+id+'\') !== -1)}" ng-disabled="(answers && !canAnswer) || (answers && (answers.indexOf(\''+id+'\') !== -1))" ng-model="response[\''+id+'\']" placeholder="'+ $translate.instant('WORKSHOP.QUESTIONNAIRE.WRITE_AN_ANSWER') +'"></md-input-container>';
            gap.html(template);
        });
        $compile(source)(scope);
        element.append(source);
    }
}
