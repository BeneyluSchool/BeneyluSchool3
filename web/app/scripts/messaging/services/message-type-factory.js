(function (angular) {
'use strict';

angular.module('bns.messaging.messageType', [])

  .factory('MessageType', MessageTypeFactory)

;

function MessageTypeFactory (_) {

  function MessageType () {
    this.form = {};

    // manage a local list of ids, not exposed to the form, because ngModel
    // overrides the array instead of modifying it
    this.tos = [];
    this.attachments = [];
  }

  /**
   * Helper function used by both controllers to parse form data
   *
   * @param  {Object} form The ngForm object
   * @return {Object} A map of API-compliant data
   */
  MessageType.prototype.getData = function () {
    return {
      draftId: this.form.id,
      subject: this.form.subject.value,
      content: this.form.content.value,
      to: _.map(this.tos, 'id').join(','),
      'resource-joined': _.map(this.attachments, 'id'),
    };
  };

  MessageType.prototype.setTos = function (ids) {
    this.tos.splice(0, this.tos.length);

    if (ids && ids.split) {
      ids.split(',').forEach(angular.bind(this, function (id) {
        id = parseInt(id, 10);
        if (id) {
          this.tos.push(id);
        }
      }));
    }
  };

  MessageType.prototype.setAttachments = function (attachments) {
    this.attachments.splice(0, this.attachments.length);
    Array.prototype.push.apply(this.attachments, attachments);
  };

  return MessageType;

}

})(angular);
