//Affichage pour navigation doit être hors de $function

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
		$(this).parent('.resource-joined').remove();	
	});
	
	//Bouton pour ajouter des ressources
	$('.resource-join').live('click',function(){
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_call_iframe' , {'type': 'join'} ),
			success: function(data){ 
				$('body').prepend(data);
			}
		});
	});
	
})