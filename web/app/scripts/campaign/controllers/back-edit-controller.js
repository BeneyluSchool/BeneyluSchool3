(function (angular) {
  'use strict';

  angular.module('bns.campaign.back.editController', [
      'bns.campaign.campaign',
      'bns.campaign.backController'
    ])

    .controller('CampaignBackEditContent', CampaignBackEditContentController)
    .controller('CampaignBackEditSidebar', CampaignBackEditSidebarController)
    .controller('CampaignBackEditActionbar', CampaignBackEditActionbarController)
    .controller('CampaignDialog', DialogController)

    .factory('campaignBackEditState', CampaignBackEditStateFactory);



  function CampaignBackEditContentController ($stateParams, moment, Restangular, campaignBackEditState, toast, $mdUtil, _, $scope, $timeout, dialog, Routing, groupId, SmsCounter) {

    var ctrl = this;

    ctrl.campaignId = $stateParams.id;
    ctrl.shared = campaignBackEditState;
    ctrl.shared.resetDefault();
    ctrl.groupId = groupId;
    ctrl.showDialog = showDialog;
    ctrl.deleteRecipients = deleteRecipients;
    ctrl.recipientsSelection = recipientsSelection;
    ctrl.refreshRecipientNumber = $mdUtil.debounce(refreshRecipientNumber, 200);
    ctrl.refreshAllUserList = $mdUtil.debounce(refreshAllUserList, 200);
    ctrl.refreshGroupDetail = $mdUtil.debounce(getGroupsDetails, 200);
    ctrl.reloadCreditUrl = Routing.generate('BNSAppSpotBundle_front', {'code':'SMS', 'origin' : 'sms'});

    init();

    function init () {
      ctrl.busy = true;

      Restangular.one('campaigns', ctrl.campaignId).get()
        .then(function success(campaign) {
          ctrl.shared.campaign = campaign;
          if (campaign.campaign.scheduled_at) {
            campaign.campaign.scheduled_at = moment(campaign.campaign.scheduled_at).toDate();
          }

          angular.forEach(campaign.recipients, function(recipient) {
            ctrl.shared.recipientSelectionIds.push(recipient.id);
            ctrl.shared.recipients.push(recipient);
          });

          angular.forEach(campaign.recipient_groups, function(group) {
            ctrl.shared.roles.push(group);
          });

          angular.forEach(campaign.recipient_lists, function(list) {
            ctrl.shared.listSelectionIds.push(list.distribution_list_id);
          });

          getGroupsDetails();
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_LOG_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
        })
      ;

      Restangular.one('groups', ctrl.groupId).all('campaigns').one('credit').get()
        .then(function success(credit) {
          ctrl.shared.credit = credit;
          ctrl.reloadCreditUrl = Routing.generate('BNSAppSpotBundle_front', {'code': 'SMS_' + credit.country, 'origin' : 'sms'});
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_CREDIT_ERROR');
          throw response;
        })
        .finally (function end() {
          ctrl.busy = false;
          refreshSmsCost();
        });

      $scope.$watch('ctrl.shared.campaign.campaign.message', function(newValue, oldValue) {
        if (newValue || newValue === '') {
          ctrl.shared.messageLength = SmsCounter.count(newValue);
          refreshSmsCost();
        }

        ctrl.shared.nbMessage = ctrl.shared.messageLength.messages;

        if (oldValue !== newValue && ctrl.shared.messageLength.messages > 10) {
          toast.error('CAMPAIGN.NB_SMS_ERROR');
        }
      });
    }

    function recipientsSelection(selectionGroup, users, lists, groups) {
      var newDistributionIds = _.map(lists, 'id');
      var newIds = _.map(users, 'id');
      var newRoles = angular.copy(groups);


      var groupsToDelete = [];
      var groupsToAdd = [];
      var userIds = _.map(users, 'id');
      var listIds = _.map(lists, 'id');

      var usersToDelete = _.difference(ctrl.shared.recipientSelectionIds, userIds);
      var listsToDelete = _.difference(ctrl.shared.listSelectionIds, listIds);

      angular.forEach(ctrl.shared.roles, function (role){
        if (!_.contains(groups, role)) {
          groupsToDelete.push(role);
        }
      });
      angular.forEach(groups, function (group){
        if (!_.contains(ctrl.shared.roles, group)) {
          groupsToAdd.push(group);
        }
      });

      if (usersToDelete.length > 0) {
        deleteRecipients(usersToDelete);
      }
      if (groupsToDelete.length > 0) {
        deleteGroups(groupsToDelete);
      }
      if (listsToDelete.length > 0) {
        deleteLists(listsToDelete);
      }
      if (userIds.length > 0) {
        addRecipients(userIds);
      }
      if (groupsToAdd.length > 0) {
        addRecipientGroups(groupsToAdd);
      }
      if (listIds.length > 0) {
        addLists(listIds);
      }


      // Update role / group list
      if (ctrl.shared.roles) {
        ctrl.shared.roles.splice(0, ctrl.shared.roles.length);
        Array.prototype.push.apply(ctrl.shared.roles, newRoles);
      }
      // Update distribution list
      if (ctrl.shared.listSelectionIds) {
        ctrl.shared.listSelectionIds.splice(0, ctrl.shared.listSelectionIds.length);
        Array.prototype.push.apply(ctrl.shared.listSelectionIds, newDistributionIds);
      }
      // Update user list
      ctrl.shared.recipientSelectionIds.splice(0, ctrl.shared.recipientSelectionIds.length);
      Array.prototype.push.apply(ctrl.shared.recipientSelectionIds, newIds);


      ctrl.shared.recipients = users;
      getGroupsDetails();
    }

    function addRecipients(ids) {
      ctrl.busy = true;

      Restangular.one('campaigns', ctrl.campaignId).all('recipients').post({users_id : ids })
        .then(function success() {
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_NEW_RECIPIENT_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
          ctrl.refreshRecipientNumber();
        });
    }

    function addRecipientGroups(groups) {
      ctrl.busy = true;
      var data = [];

      angular.forEach(groups, function(group){
        var groupInfo = { group_id : group.group.id , role : group.type } ;
        data.push(groupInfo);
      });

      Restangular.one('campaigns', ctrl.campaignId).all('recipients_groups').post({groups : data })
        .then(function success() {
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_NEW_RECIPIENT_GROUP_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
          ctrl.refreshGroupDetail();
        });
    }

    /**
     *
     * @param ids
     */
    function addLists(ids) {
      ctrl.busy = true;

      Restangular.one('campaigns', ctrl.campaignId).all('recipients_lists').post({'list_ids' : ids })
        .then(function success() {
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_NEW_RECIPIENT_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
          ctrl.refreshGroupDetail();
        });
    }

    /**
     * delete a distribution list from the campaign
     * @param ids
     */
    function deleteLists(ids) {
      ctrl.busy = true;

      Restangular.one('campaigns', ctrl.campaignId).all('recipients_lists').remove({'list_ids[]' : ids })
        .then(function success() {
          // remove real list
          angular.forEach(ids, function(id){
            _.pull(ctrl.shared.listSelectionIds, id);
          });
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.DELETE_RECIPIENT_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
          ctrl.refreshGroupDetail();
        });
    }

    /**
     * delete a group/role
     * @param groups
     */
    function deleteGroups(groups) {
      ctrl.busy = true;

      var ids = _.map(groups, function(item) {
        return item.group.id + '_' + item.type;
      });

      Restangular.one('campaigns', ctrl.campaignId).all('recipients_groups').remove({'groups[]' : ids})
        .then(function success() {
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.DELETE_RECIPIENT_GROUP_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
          ctrl.refreshGroupDetail();
        });
    }

    /**
     * remove individual user from the campaign
     * @param ids
     */
    function deleteRecipients(ids) {
      ctrl.busy = true;

      Restangular.one('campaigns', ctrl.campaignId).all('recipients').remove({'users_id[]' : ids })
        .then(function success() {
          angular.forEach(ids, function(id){
            _.pull(ctrl.shared.allUsersIds, id);
            _.pull(ctrl.shared.recipientSelectionIds, id);
            _.remove(ctrl.shared.allUsers, function(user){
              return user.id === id;
            });
            _.remove(ctrl.shared.recipients, function(recipient){
              return recipient.id === id;
            });
          });
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.DELETE_RECIPIENT_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
          ctrl.refreshRecipientNumber();
        });
    }

    /**
     * get groups / roles detail
     */
    function getGroupsDetails() {
      ctrl.busyRecipient = true;
      Restangular.one('campaigns', ctrl.campaignId).all('details').getList()
        .then(function success(groups) {
          ctrl.shared.groupsAndLists = groups;
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_RECIPIENT_ERROR');
          throw response;
        })
        .finally(function end() {
          refreshExpander();
          ctrl.refreshAllUserList();
          ctrl.busyRecipient = false;
        });
    }

    function refreshAllUserList() {
      // Update the number of unique recipients
      ctrl.refreshRecipientNumber();

      ctrl.shared.allUsers.length = 0;
      ctrl.shared.allUsersIds.length = 0;

      //filter by main role teacher, parent or assistant
      //merge campaign recipients and recipients from list/group
      angular.forEach(ctrl.shared.groupsAndLists, function(list){
        angular.forEach(list.roles, function(role){
          angular.forEach(role.users, function(user){
            if(user.main_role === 'teacher' || user.main_role === 'parent' || user.main_role === 'assistant') {
              if (ctrl.shared.allUsersIds.indexOf(user.id) === -1) {
                ctrl.shared.allUsers.push(user);
              }
              ctrl.shared.allUsersIds.push(user.id);
            }
          });
        });
      });

      angular.forEach(ctrl.shared.recipients, function(recipient) {
        if(recipient.main_role === 'teacher' || recipient.main_role === 'parent' || recipient.main_role === 'assistant') {
          if (ctrl.shared.allUsersIds.indexOf(recipient.id) === -1) {
            ctrl.shared.allUsers.push(recipient);
          }
          ctrl.shared.allUsersIds.push(recipient.id);
        }
      });
    }

    function showDialog(param) {
      if (angular.isArray(param)) {
        ctrl.shared.users = param;
        return dialog.custom({
          templateUrl: 'views/campaign/back/edit-many-users-dialog.html',
          locals: {
            close: dialog.hide,
          },
          controller: 'CampaignDialog',
          controllerAs: 'ctrl',
          bindToController: true,
          clickOutsideToClose: true,
        })
          .then(updateNbRecipient)
        ;
      } else {
        ctrl.shared.user = param;
        return dialog.custom({
          templateUrl: 'views/campaign/back/edit-user-dialog.html',
          locals: {
            close: dialog.hide,
          },
          controller: 'CampaignDialog',
          controllerAs: 'ctrl',
          bindToController: true,
          clickOutsideToClose: true,
        })
          .then(updateNbRecipient)
        ;
      }
    }

    function updateNbRecipient () {
      return Restangular.one('campaigns', ctrl.campaignId).one('recipients', '').get()
        .then(function success (data) {
          ctrl.shared.campaign.campaign.nb_recipient = data.nb_recipient;
        })
      ;
    }

    function refreshExpander() {
      $timeout(function(){
        $scope.$broadcast('track.height');
      },100, true);
    }

    function refreshSmsCost() {
      if (angular.isDefined(ctrl.shared.credit.balance)) {
        ctrl.shared.messageCost = ctrl.shared.messageLength.messages * ctrl.shared.campaign.campaign.nb_recipient;
        ctrl.shared.remainingCredit = ctrl.shared.credit.balance - ctrl.shared.messageCost;
      }
    }

    function refreshRecipientNumber() {
      Restangular.one('campaigns', ctrl.campaignId).get()
        .then(function success(campaign) {
          ctrl.shared.campaign.campaign.nb_recipient = campaign.campaign.nb_recipient;
        })
        .finally(function(){
          refreshSmsCost();
        })
      ;

    }
  }


  function DialogController(moment, campaignBackEditState, $mdDialog, Restangular, _, toast, $state) {
     var ctrl = this;
     ctrl.shared = campaignBackEditState;
     ctrl.editUser = editUser;
    ctrl.editUsers = editUsers;
    ctrl.submit = submit;

    if (ctrl.shared.campaign.campaign.scheduled_at) {
      ctrl.scheduledAtDate = moment(ctrl.shared.campaign.campaign.scheduled_at);
    }

    function editUser() {
     ctrl.busy = true;

      var data = {
        first_name: ctrl.shared.user.first_name,
        last_name: ctrl.shared.user.last_name,
        email: angular.isUndefined(ctrl.shared.user.email) ? null : ctrl.shared.user.email,
        phone: angular.isUndefined(ctrl.shared.user.phone) ? null : ctrl.shared.user.phone
      };

        if (!data) {
        return;
      }

      Restangular.one('users', ctrl.shared.user.id ).one('campaign-type', ctrl.shared.campaign.campaign.type_name).all('fast-edit').patch(data)
        .then(function success() {
          ctrl.errors = [];
          $mdDialog.hide();
        }).catch(function error(response) {
        if (response.data.errors) {
          ctrl.errors = response.data.errors.children;
        }
        throw response;
        }).finally(function end() {
          ctrl.busy = false;
        });
    }

    function editUsers() {
      ctrl.busy = true;

      var data = {};
      angular.forEach(ctrl.shared.users, function(user){
        var userInfo = { first_name : user.first_name , last_name : user.last_name,
          email : angular.isUndefined(user.email) ? null : user.email, phone : angular.isUndefined(user.phone) ? null : user.phone} ;

        data[user.id] = userInfo;
      });

      if (!data) {
        return;
      }

      Restangular.one('users').one('campaign-type', ctrl.shared.campaign.campaign.type_name).all('fast-edit').patch({ 'users' : data})
        .then(function success() {
          ctrl.errors = [];

          $mdDialog.hide();
        }).catch(function error(response) {
          if (response.data.errors) {
            ctrl.errors = response.data.errors.children.users.children;
          }
          throw response;
        }).finally(function end() {
        ctrl.busy = false;
      });

    }

    function submit () {
      ctrl.busy = true;
      var data = {
        name : ctrl.shared.campaign.campaign.name,
        title : ctrl.shared.campaign.campaign.title,
        message : ctrl.shared.campaign.campaign.message,
        scheduled_at : ctrl.shared.campaign.campaign.scheduled_at ? moment(ctrl.shared.campaign.campaign.scheduled_at).format() : null,
        'resource-joined': _.map(ctrl.shared.campaign.campaign._embedded.attachments, 'id'),
      };


      Restangular.one('campaigns', ctrl.shared.campaign.campaign.id).patch(data)
        .then(function success() {
          Restangular.one('campaigns', ctrl.shared.campaign.campaign.id).all('send').post()
            .then(function success() {
              if (ctrl.shared.campaign.campaign.scheduled_at) {
                toast.success('CAMPAIGN.SCHEDULE_SUCCESS');
              } else {
                toast.success('CAMPAIGN.SEND_SUCCESS');
              }
              $mdDialog.hide();
              $state.go('app.campaign.back.show', { id: ctrl.shared.campaign.campaign.id});

            })
            .catch(function error(response) {
              if (ctrl.shared.campaign.campaign.scheduled_at) {
                toast.error('CAMPAIGN.SCHEDULE_ERROR');
              } else {
                toast.error('CAMPAIGN.SEND_ERROR');
              }
              $mdDialog.hide();
              throw response;
            });
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.FORM_ERROR');
          $mdDialog.hide();
          ctrl.shared.error = response.data.errors.children;
          throw response;
        })
        .finally (function end() {
          ctrl.busy = false;
        });
    }
  }

  function CampaignBackEditSidebarController (campaignBackEditState) {
    var ctrl = this;
    ctrl.shared = campaignBackEditState;
  }

  function CampaignBackEditActionbarController (groupId, campaignBackEditState, Restangular, _, $state, dialog, toast) {
    var ctrl = this;
    ctrl.groupId = groupId;
    ctrl.deleteCampaign = deleteCampaign;
    ctrl.showSubmitDialog = showSubmitDialog;
    ctrl.saveInDraft = saveInDraft;
    ctrl.shared = campaignBackEditState;

    function deleteCampaign () {
      ctrl.busy = true;

      //todo dialog ?
      Restangular.one('groups', ctrl.groupId).one('campaigns').remove({'ids[]' : ctrl.shared.campaign.campaign.id})
        .finally (function end() {
          ctrl.busy = false;
          $state.go('app.campaign.back.list');
        });
    }



    function saveInDraft() {
      ctrl.busy = true;

      var data = {
        name : ctrl.shared.campaign.campaign.name,
        title : ctrl.shared.campaign.campaign.title,
        message : ctrl.shared.campaign.campaign.message,
        status : 'DRAFT',
        'resource-joined': _.map(ctrl.shared.campaign.campaign._embedded.attachments, 'id'),
      };


      Restangular.one('campaigns', ctrl.shared.campaign.campaign.id).patch(data)
        .then(function success() {
          toast.success('CAMPAIGN.SAVE_IN_DRAFT_SUCCESS');
          $state.go('app.campaign.back.show', { id: ctrl.shared.campaign.campaign.id});
        }).catch(function error(response) {
          toast.error('CAMPAIGN.SAVE_IN_DRAFT_ERROR');
          ctrl.shared.error = response.data.errors.children;
          throw response;
        })
        .finally (function end() {
          ctrl.busy = false;

        });

    }

    function showSubmitDialog() {
      if (ctrl.busy) {
        return;
      }

      if (ctrl.shared.campaign.campaign.type_name === 'SMS' && !ctrl.shared.remainingCredit) {
        return;
      }

      return dialog.custom({
        templateUrl: 'views/campaign/back/send-dialog.html',
        locals: {
          close: dialog.hide,
          groupId: ctrl.groupId
        },
        controller: 'CampaignDialog',
        controllerAs: 'ctrl',
        bindToController: true,
        clickOutsideToClose: true,
      });
    }

  }

  function CampaignBackEditStateFactory () {
    var currentValue = {};
    var defaultValue = {
      campaign: '',
      error: '',
      user: '',
      users: [],
      recipients: [],
      recipientSelectionIds: [],
      allUsers: [],
      allUsersIds: [],
      roles: [],
      listSelectionIds: [],
      groupSelectionIds: [],
      groupsAndLists: [],
      credit: 0,
      remainingCredit: 0,
      messageCost: 0,
      number: 0,
      numberTotal: 140,
      nbMessage: 0,
      nbMessageTotal: 10,
      messageLength: {},
      resetDefault: function() {
        angular.copy(defaultValue, currentValue);
      }
    };

    defaultValue.resetDefault();

    return currentValue;
  }

})(angular);
