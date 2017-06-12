var page = 1;
var currentBox = 'inbox';
var searchedWord = "";
var is_in_search = false;
var modalEvent = false;

//Actions de click sur les différents boutons, puis déclenchement des actions
$(function(){
	//Topbar
	$('#messaging-top-back-inbox').live('click',function(e){
		e.preventDefault();
		showInbox();
	});
	
	$('#messaging-top-back-outbox').live('click',function(e){
		e.preventDefault();
		showOutbox();
	});
	
	$('#messaging-top-back-deletedbox').live('click',function(e){
		e.preventDefault();
		showDeletedbox();
	});
	
	$('#messaging-top-back-draftbox').live('click',function(e){
		e.preventDefault();
		showDraftbox();
	});
	
	$('#messaging-top-new-message').live('click',function(e){
		e.preventDefault();
		newMessage();
	});
    
	$('#messaging-top-draft').live('click',function(e){
        e.preventDefault();
        
        var objet = $('.receiver').val();
        
        //verifie que le champ objet n'est pas remplie d'espace
        if (objet.length && !objet.replace(/^\s+|\s+$/g, "").length)
        {
            //si c'est le cas on le vide
            $('.receiver').val('');
        }
        
        var formUrl = Routing.generate('BNSAppMessagingBundle_front_light_ajax_message_save_draft');
        //On change la destination du formulaire
        $('#messaging-send-form').attr('action',formUrl);
        //Reload = draft
        $('#messaging-send-form').attr('data-target',"drafted");
        $('#messaging-new-message-submit').trigger('click');
  	});
	
	$('#messaging-top-send').live('click',function(e){
		e.preventDefault();
		
		if($('#messaging_message_to').val() == ""){
			$('#tos-error').show();
		}else{
			$('#messaging-new-message-submit').trigger('click');
		}
	});
	
	$('#messaging-top-answer').live('click',function(e){
		e.preventDefault();
		$('#messaging-answer-message-submit').trigger('click');
	});
	
	$('#messaging-top-delete').live('click',function(e){
		e.preventDefault();
                // delete message
                deleteConversation($('#messaging-conversation-content').attr('data-messaging-conversation-id'));
	});
	
	$('#messaging-modal-draft-delete').live('click',function(e){
		e.preventDefault();
                // delete draft
                deleteDraft($('#messaging_message_draftId').val());                        
	});
	
	$('#messaging-top-restore').live('click',function(e){
		e.preventDefault();
		restoreConversation($('#messaging-conversation-content').attr('data-messaging-conversation-id'));
	});
	
	$('#messaging-top-search-submit').live('click',function(e){
		e.preventDefault();
		searchMessages($('#messaging-top-search-input').val());
	});
	
	$('#messaging-top-back-search').live('click',function(e){
		e.preventDefault();
		searchMessages(searchedWord);
	});
	
	//Entrée = submit du formulaire
	$("#messaging-top-search-input").keypress(function(event){
	  if ( event.which == 13 )
		searchMessages($('#messaging-top-search-input').val());
	  
	});
	
	//Sidebar
	$('#messaging-sidebar-outbox').live('click',function(e){
		e.preventDefault();
		page = 1;
		showOutbox();
	});
	
	$('#messaging-sidebar-draftbox').live('click',function(e){
		e.preventDefault();
		page = 1;
		showDraftbox();
	});
	
	$('#messaging-sidebar-deletedbox').live('click',function(e){
		e.preventDefault();
		page = 1;
		showDeletedbox();
	});
	
	$('#messaging-sidebar-inbox').live('click',function(e){
		e.preventDefault();
		page = 1;
		showInbox();
	});
	
	//In content
	
	$('.messaging-container-conversation').live('click',function(e){
		e.preventDefault();
		//Selon existence de la classe dispatch vers deleted ou non
		if($(this).hasClass('conversation-deleted'))
			showConversationDeleted($(this).attr('data-conversation-id'));
		else
			showConversation($(this).attr('data-conversation-id'));
	});
	
	$('.messaging-container-message').live('click',function(e){
		e.preventDefault();
		showMessage($(this).attr('data-message-id'));
	});
	
	$('.messaging-container-message-draft').live('click',function(e){
		e.preventDefault();
		showMessageDraft($(this).attr('data-message-id'));
	});
	//Pagination
	$('.messaging-paginate').live('click',function(e){
		e.preventDefault();
		page = $(this).attr('data-page');
		reloadBox(currentBox);
	});
		
	$(window).resize(function() {
		adjustHeight();
	});
	
	$(window).scroll(function(){

		var height = window.pageYOffset;
		
		if($.browser.msie && parseInt($.browser.version) <= 8)
		{
			height = window.document.documentElement.scrollTop;
		}
		
		if(currentBox != 'inbox'){
			$('.container-message').css('margin-top','-' + height + 'px');
			adjustHeight();
		}else{
			var readHeight = $('#messaging-messages-none-read > .container-message').height();
			if(height > readHeight){
				var noneReadHeight = height - readHeight;
			}else{
				var noneReadHeight = 0;
			}
			$('#messaging-messages-read > .container-message').css('margin-top','-' + noneReadHeight + 'px');
			$('#messaging-messages-none-read > .container-message').css('margin-top','-' + height + 'px');
			
			$('#messaging-conversation-content.container-message').css('margin-top','-' + height + 'px');
		}
		
	});
	
	$('#messaging_message_to').live('change',function(){
		updateAddUsersButton();
	});
});

function adjustHeight()
{
	var height = 0;
	
	if($('#messaging-messages-read').length){
		height += $('#messaging-messages-read').height();
		height += 50;
	}
	if($('#messaging-messages-none-read').length){
		height += $('#messaging-messages-none-read').height();
		height += 50;
	}
	
	if( $('.container-message').length){
		if(height == 0){
			height = $('.container-message').height();
		}
		height += $('.container-message').first().offset().top + 120;
	}
	
	if($('.pagination').length){
		height += $('.pagination').height();
		height += 50;
	}
	
	$('#main-scrollbar').height(height + 'px');	
}


/////////////   4 boîtes : réception, envoi, brouillons, suppression

//Boîte de réception

function showInbox(data){	
	adjustHeight();
	currentBox = 'inbox';
	$('#messaging-top-search-input').val('');
	hideTopbarButtons();
	$('#messaging-top-new-message').show();
	$('.messaging-top-search').show();
	inactiveSidebarButtons();
	$('#messaging-sidebar-inbox').addClass('active');
	showMainLoader();
	if(data != null){
		$('#messaging-main-container').html(data);
		hideMainLoader();
	}else{
		$.ajax({
			url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_inbox',{"page" : page}),
			success: function(data){
				$('#messaging-main-container').html(data);
				hideMainLoader();
			}
		});
	}
}

//Boîte d'envoi

function showOutbox(data){
	currentBox = 'outbox';
	$('#messaging-top-search-input').val('');
	hideTopbarButtons();
	$('#messaging-top-new-message').show();
	$('.messaging-top-search').show();
		
	showMainLoader();
	
	inactiveSidebarButtons();
	
	$('#messaging-sidebar-outbox').addClass('active');
	
	if(data != null){
		$('#messaging-main-container').html(data);
		hideMainLoader();
	}else{
		$.ajax(
		{
			url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_outbox',{"page" : page}),
			success: function(data){
				$('#messaging-main-container').html(data);
				hideMainLoader();
			}
		});
	}
}

//Boîte de brouillons

function showDraftbox(data){
	currentBox = 'draftbox';
	$('#messaging-top-search-input').val('');
	hideTopbarButtons();
	$('#messaging-top-new-message').show();
	$('.messaging-top-search').show();
		
	showMainLoader();
	
	inactiveSidebarButtons();
	
	$('#messaging-sidebar-draftbox').addClass('active');
	
	if(data != null){
		$('#messaging-main-container').html(data);
		hideMainLoader();
	}else{
		$.ajax(
		{
			url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_draftbox',{"page" : page}),
			success: function(data){
				$('#messaging-main-container').html(data);
				hideMainLoader();
			}
		});
	}
}
//Boîte de suppression

function showDeletedbox(data){
	currentBox = 'deletedbox';
	hideTopbarButtons();
	$('#messaging-top-new-message').show();
	$('.messaging-top-search').show();
		
	inactiveSidebarButtons();
	
	$('#messaging-sidebar-deletedbox').addClass('active');
	
	showMainLoader();
	
	if(data != null){
		$('#messaging-main-container').html(data);
		hideMainLoader();
	}else{
		$.ajax(
		{
			url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_deletedbox',{"page" : page}),
			success: function(data){
				$('#messaging-main-container').html(data);
				hideMainLoader();
			}
		});
	}
}

///////////// Visualisation des contenus : Conversation (deletedbox et inbox) et message (draftbox et outbox)

function showConversation(conversationId){
	hideTopbarButtons();
	$('#messaging-top-new-message').show();
	if(!is_in_search){
		$('#messaging-top-back-inbox').show();
		$('#messaging-top-delete').show();
	}else
		$('#messaging-top-back-search').show();
	
	$('#messaging-top-answer').show();
	
	showMainLoader();
	
	//tinyMCE.execCommand("mceRemoveControl", true, "messaging_answer_answer");
	
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_conversation', { conversationId: conversationId }),
		success: function(data){
			$('#messaging-main-container').html(data);
			hideMainLoader();
			$("#messaging-answer-form").trigger('formLoaded');
			adjustHeight();
		}
	});
}

function showConversationDeleted(conversationId){
	hideTopbarButtons();
	$('#messaging-top-new-message').show();
	$('#messaging-top-back-deletedbox').show();
	$('#messaging-top-restore').show();
	
	showMainLoader();
	
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_conversation', { conversationId: conversationId }),
		success: function(data){
			$('#messaging-main-container').html(data);
			hideMainLoader();
			adjustHeight();
		}
	});
}

function deleteDraft(draftId){
	showMainLoader();
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_draft_delete', { draftId: draftId }),
		success: function(data){
			showInbox(data);
			hideMainLoader();
		}
	});
}

function deleteConversation(conversationId){
	showMainLoader();
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_conversation_delete', { conversationId: conversationId }),
		success: function(data){
			showInbox(data);
			hideMainLoader();
		}
	});
}

function restoreConversation(conversationId){
	showMainLoader();
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_conversation_restore', { conversationId: conversationId }),
		success: function(data){
			showDeletedbox(data);
			hideMainLoader();
		}
	});
}

function showMessage(messageId){
	hideTopbarButtons();
	$('#messaging-top-new-message').show();
	$('#messaging-top-back-outbox').show();
		
	showMainLoader();
		
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_message', { messageId: messageId }),
		success: function(data){
			$('#messaging-main-container').html(data);
			hideMainLoader();
			adjustHeight();
		}
	});
}


function showMessageDraft(draftId,data){
	hideTopbarButtons();
	$('#messaging-top-back-draftbox').show();
	$('#messaging-top-send').show();
	$('#messaging-top-draft').show();
	$('#messaging-top-draft-delete').show();
		
	showMainLoader();
	
	tinyMCE.execCommand("mceRemoveControl", true, "messaging_message_content");
	
	if(draftId != null){
	
		$.ajax(
		{
			url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_message_edit_draft', { draftId: draftId }),
			success: function(data){
				$('#messaging-main-container').html(data);
				$("#messaging-send-form").trigger('formLoaded');
				hideMainLoader();
			}
		});
	}else{
		$('#messaging-main-container').html(data);
		$("#messaging-send-form").trigger('formLoaded');
		hideMainLoader();
	}
}

function newMessage(){
	currentBox = 'outbox';
	hideTopbarButtons();
	$('#messaging-top-back-inbox').show();
	$('#messaging-top-send').show();
	$('#messaging-top-draft').show();
	inactiveSidebarButtons();
	showMainLoader();
	
	tinyMCE.execCommand("mceRemoveControl", true, "messaging_message_content");
	
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_new_message'),
		success: function(data){
			$('#messaging-main-container').html(data);
			$("#messaging-send-form").trigger('formLoaded');
		}
	})
}

function prepareSendForm(){
	$('#messaging-send-form').ajaxForm({
		beforeSubmit: function(){ 
			showMainLoader();
		},
		success: function(data){
			if($('#messaging-send-form').attr('data-target') == 'inbox'){
				showInbox(data);
			}
			if($('#messaging-send-form').attr('data-target') == 'drafted'){
				showMessageDraft(null,data);
			}
		}
	});
	//Tips pour l'envoi en ajax du formulaire : on trigger le save de TinyMce
	$('#messaging-send-form').bind('form-pre-serialize', function(e) {
		tinyMCE.triggerSave();
	});
}

function submitSendForm(){
	$('#messaging-send-form').submit(function(){});	
}

function prepareAnswerForm(){
	$('#messaging-answer-form').ajaxForm({
		beforeSubmit: function(){ 
			showMainLoader();
		},
		success: function(data){
			showInbox();
		}
	});
	//Tips pour l'envoi en ajax du forumulaire : on trigger le save de TinyMce
	$('#messaging-answer-form').bind('form-pre-serialize', function(e) {
		tinyMCE.triggerSave();
	});
}
//Recherche

function searchMessages(word){
	
	currentBox = 'search';
	searchedWord = encodeURIComponent(word);
	hideTopbarButtons();
	$('.messaging-top-search').show();
	$('#messaging-top-new-message').show();
	inactiveSidebarButtons();
	showMainLoader();
	is_in_search = true;
	$.ajax(
	{
		url: Routing.generate('BNSAppMessagingBundle_front_light_ajax_search_message',{word : searchedWord,page : page}),
		success: function(data){
			$('#messaging-main-container').html(data);
			hideMainLoader();
		}
	});
}

//////////////  Fonctions génériques : habillage notamment


function reloadBox(type){
	if(type == "inbox")
		showInbox();
	if(type == "draftbox")
		showDraftbox();
	if(type == "outbox")
		showOutbox();
	if(type == "deletedbox")
		showDeletedbox();
	if(type == "search")
		searchMessages(searchedWord);
}

//Gestion des loaders
function showMainLoader(){
	$('#messaging-main-container').hide();
	$('#messaging-main-loader').show();
}
function hideMainLoader(){
	$('#messaging-main-loader').hide();
	$('#messaging-main-container').show();
	setTimeout(function(){
		adjustHeight();		
	},500);
}
//Gestion de l'affichage des boutons'
function inactiveSidebarButtons(){
	is_in_search = false;
	$('.messaging-sidebar-button').removeClass('active');
}
function hideTopbarButtons(){
	$('.messaging-top-button').hide();
}
//Gestion du nombre de messages nons lus
function updateInboxSidebar(number){
	$('#messaging-sidebar-inbox .number-message').remove();
	if(number > 0){
		$('#messaging-sidebar-inbox').append('<span class="number-message">' + number + '</span>');
	}
}
function updateDraftboxSidebar(number){
	$('#messaging-sidebar-draftbox .number-message').remove();
	if(number > 0){
		$('#messaging-sidebar-draftbox').append('<span class="number-message">' + number + '</span>');
	}
}
//Mise à jour du nombre de destinataires
function updateAddUsersButton(){
	var val = $('#messaging_message_to').val();
}
//Gestion des destinataires

function initTos(){
	var ids = $('#messaging_message_to').val().split(',');
	$.each(ids, function() {
		$('a.selectable.checkbox[data-user-id=' + this + ']').trigger('click');
	});
	$('#messaging-write-new-msg-choose-modal-add-selected-user').trigger('click');
}

function manageTos(){
	$('#messaging_message_to').change(function(){
		updateTos();
	});

	$('.front-select > span').live('click',function(){
		var id = $(this).parent().attr('data-user-id');
		$('.selected-user-container:first').find('.cancel[data-user-id="' + id + '"] > span').trigger('click');
		$('#messaging-write-new-msg-choose-modal-add-selected-user').trigger('click');
		if($('#tos-list').find('.front-select').length == 0){
			$('#tos-label').show();
			$('#tos-list').hide();	
			$('br.tos-user').remove();
		}
	});
}

//check du champs des destinataires
function updateTos(){
	$('#tos-error').hide();
	$('.front-select').remove();
	var ids = $('#messaging_message_to').val().split(',');
	if(ids != ""){
		$('#tos-label').hide();
		$('#tos-list').show();
		$.each(ids, function() {
            if ($('#tos-list').find('a[data-user-id = ' + this + ']').length == 0) {
			    $('#tos-list').append($('.selected-user-container').find($('.user-block.cancel[data-user-id="' + this + '"]')).first().clone().addClass('front-select'));
            }
		});	
	}else{
		$('#tos-label').show();
		$('#tos-list').hide();	
	}
	adjustHeight();
}