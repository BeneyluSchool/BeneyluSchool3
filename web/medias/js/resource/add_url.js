$(function(){

	$('#input-destination-choice').val($('.destination-choice').attr('id'));

	//Soupission du formulaire si bouton actif
	$('#add-label-submit').live('click',function(event){
		event.preventDefault();
		if(!$(this).hasClass('disabled')){
			$('.layer').show();
			$('#results').hide();
			$('#validation-buttons').hide();
			$('#urls-form').submit();
		}
	});

	//Ajout d'un lien / vidéo
	$('#add-url-form').submit(function(){
		$('#informations').hide();
		$('.layer').show();

		$.post(Routing.generate('BNSAppResourceBundle_add_url_submit'),
		{
			url: $('#add-url-form-url').val()
		},			
		function complete(data)
		{
			$('#results').html(data + $('#results').html());
			$('#results').show();
			$('.layer').hide();
			$('#add-url-form-url').val("");
			checkLoadButton();
			setTimeout(function(){$(".url-error").alert('close');},5000);				
		})
	});
	//au clic sur Annuler au supprimer l'élement du dom
	$(document).on("click",'.reset-url', function(){
		$(this).parent().parent().remove();
		checkLoadButton();
	});
});