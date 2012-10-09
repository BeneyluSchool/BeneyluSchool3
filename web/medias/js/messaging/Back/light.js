
var page = 1;
var currentStatus = 'IN_MODERATION';

//Actions de click sur les différents boutons, puis déclenchement des actions
$(function(){
	//Sidebar
	$('.messaging-back-sidebar-filter').live('click',function(e){
		var $row = $(e.currentTarget),
			$parent = $row.parent().parent(),
			$checkbox = $row.find('.select');

		resetFilterClass()

		// Show loader
		var $loader = $parent.find('.loader');
		$loader.fadeIn('fast');
		$checkbox.toggleClass('checked');
		
		showMessages($(this).attr('data-filter-status')).done(function(){
			$loader.fadeOut('fast');
		});
		
		if($(this).attr('data-filter-status') == "REJECTED"){
			$('#messaging-rejected-delete').show();
			$('#messaging-moderate-validation').hide();
		}else{
			$('#messaging-rejected-delete').hide();
			$('#messaging-moderate-validation').show();
		}
		return false;
	});
	
	$('.messaging-back-paginate').live('click',function(e){
		e.preventDefault();
		page = $(this).attr('data-page');
		showMessages(currentStatus);
	});
	
	$('.messaging-moderation-message-action').live('click',function(e){
		e.preventDefault();
		messagesAction($(this).attr('data-message-id'),$(this).attr('data-type'));
	});
	
	$('.moderation-action-button').live('click',function(e){
		e.preventDefault();
		var $this = $(e.currentTarget);
		if ($this.hasClass('loading')) {
			return false;
		}
		$this.addClass('loading');
				
		$.ajax({
			url: Routing.generate('BNSAppMessagingBundle_back_light_rule_toggle',{value : !$this.hasClass('off') ? "true" : "false", groupId : $this.attr('data-group-id') , type: $(this).attr('data-type') }),
			type: 'GET',
			dataType: 'html',
			success: function (data)
			{
				$this.replaceWith(data);
			}
		}).done(function ()
		{
			$this.removeClass('loading');
		});
	})
});

function resetFilterClass(){
	$('.messaging-back-sidebar-filter .select').removeClass('checked');
}

//Affichage des messages

function showMessages(filter){
	currentStatus = filter;
	
	showMainLoader();
	
	return $.ajax({
		url: Routing.generate('BNSAppMessagingBundle_back_light_messages',{type : filter, page : page}),
		success: function(data){
			$('#messaging-moderation-container').html(data);
			hideMainLoader();
		}
	});
}

function messagesAction(messageId,type){
	$('#messaging-message-' + messageId).hide('blind');
	$.ajax({
		url: Routing.generate('BNSAppMessagingBundle_back_light_message_toggle',{messageId : messageId, type: type, page : page, currentType: currentStatus}),
		success: function(data){
			$('#messaging-moderation-container').html(data);
		}
	});
}

function ruleToggle(groupId,type,value){
	$.ajax({
		url: Routing.generate('BNSAppMessagingBundle_back_light_rule_toggle',{groupId : groupId, type: type, value: value}),
		success: function(data){
			$('#moderation-action-button-' + type + '-' + groupId ).replaceWith(data);
		}
	});
}

//////////////  Fonctions génériques : habillage notamment

//Gestion des loaders
function showMainLoader(){
	$('#messaging-moderation-container').hide();
	$('#messagin-back-main-loader').show();	
}
function hideMainLoader(){
	$('#messagin-back-main-loader').hide();
	$('#messaging-moderation-container').show();
}