//Affichage pour navigation doit être hors de $function

// Fonction d'évènement pour le TinyMCE
var onTinyMCEKeypress = function () {};

function tinymce_button_resource(ed) {

	parent.ed = ed;

	$.ajax({
		url: Routing.generate('BNSAppResourceBundle_call_iframe' , {'type': 'insert'} ),
		success: function(data){ 
			$('body').prepend(data);
			$('#resource-iframe').load(function() {
				$('.close-resource-iframe').bind("click",function(){ });
			});
		}
	});
}

$(function(){ 

	//Bouton de suppression d'une ressource jointe
	$('.resource-joined-delete').live('click',function(){
		$(this).parent('.resource-joined').hide('blind',1500);
		$(this).parent().find('input').remove();
	});
	
	//Bouton pour ajouter des ressources
	$('.resource-join').live('click',function(e){
		e.preventDefault();
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_call_iframe' , {'type': 'join','reference': $(this).parent().next('.resource-list').attr('id')} ),
			success: function(data){ 
				$('body').prepend(data);
			}
		});
	});
	
	//Bouton pour sélection de ressources
	$('.resource-selection').live('click',function(e){
		e.preventDefault();
		var final_id = $(this).attr('data-final-id');
		var callback = $(this).attr('data-callback');
		var allowed_type = $(this).attr('data-allowed-type');
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_select_file_caller', { 'final_id': final_id, 'callback': callback, allowed_type: allowed_type }),
			success: function(data){ 
				$('body').prepend(data);
			}
		});
	});
	
	//Suppression des ressources jointes
	$('.resource-joined-delete').live('click',function(){
		$(this).parent('attachment').remove();
	});
	//Afficher les vidéos liées
	$('.resource-attachment-embedded-video-launcher').live('click',function(event){
		event.preventDefault();
		$(".resource-attachment-embedded-video").after().first().show('blind',null,1500);
		$(".resource-attachment-embedded-video-hidder").after().first().show();
		$(this).hide();
	});
	//Cacher les vidéos liées
	$('.resource-attachment-embedded-video-hidder').live('click',function(event){
		event.preventDefault();
		$(".resource-attachment-embedded-video").after().first().hide();
		$(".resource-attachment-embedded-video-launcher").before().first().show();
		$(this).hide();
	});
})

