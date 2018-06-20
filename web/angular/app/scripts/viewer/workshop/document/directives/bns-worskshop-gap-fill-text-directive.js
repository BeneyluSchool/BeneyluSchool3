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
            canAnswer: '=',
            timeStopped: '='
        },
        link: postLink
    };

    function postLink (scope, element) {
        var source = angular.element(scope.text);

        angular.forEach(source.find('span[data-bns-gap-guid]'), function(gap) {
            gap = angular.element(gap);
            var id = gap.attr('data-bns-gap-guid');
            var template = '<md-input-container class="gap-fill-input" bns-no-float bns-no-message md-no-float><input type="text" autocomplete="off" ng-class="{\'good-answer\': answers && (answers.indexOf(\''+id+'\') !== -1), \'wrong-answer\': answers && (!answers.indexOf(\''+id+'\') !== -1)}" ng-disabled="(answers && !canAnswer) || (answers && (answers.indexOf(\''+id+'\') !== -1)) || timeStopped" ng-model="response[\''+id+'\']" placeholder="'+ $translate.instant('WORKSHOP.QUESTIONNAIRE.ANSWER_WRITE_AN_ANSWER') +'"></md-input-container>';
            gap.html(template);
        });
        $compile(source)(scope);
        element.append(source);
    }
}
