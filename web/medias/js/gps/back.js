var selected_place = null;

$(function(){ 		
	//Activation / désactivation d'une categorie
	$(".load-sortable").on('click',".gps-category-toggle",function(){
		$this = $(this);
		var id = $(this).parent().parent().attr('id').split('_')[1];
		$this.addClass('loading');
		$.post(
			Routing.generate('BNSAppGPSBundle_back_category_toggle_activation', {}),
			{'category_id': id}, 
			function complete(returnedId)
			{
				$('#list_' + returnedId).find(".gps-category-toggle").toggleClass('off');
				$('#list_' + returnedId).find(".gps-category-toggle").removeClass('loading');
			}
		);
	}); 
	
	
	//Activation / désactivation des filtres de catégories
	$(".gps-category-filter").find('.gps-choose-category').click(function(){
		if(!$(this).find('.select').hasClass('checked')){
			var categoryId = $(this).attr('data-category-id');
		}else{
			var categoryId = null;
		}
		$(this).find('.select').toggleClass('checked');
		$(".gps-choose-category:not([data-category-id='" + $(this).attr('data-category-id') +"'])").find('.select').removeClass('checked');
		
		showLayer();		
		
		$.ajax({
			url: Routing.generate('BNSAppGPSBundle_back_places_list',{'categoryId' : categoryId}),
			success: function(data){
				$('#gps-places-list').html(data);
				hideLayer();
			}
		});
		
		
	});
	
	
	//Sélection d'une catégorie sur la formulaire d'édition / création
	$(".gps-category-label").live('click',function(){
		$("#gps_place_form_gps_category_id").val($(this).find('input').val());
	});
	
	$('.gps-place-switch').click(function (e)
	{
		var $this = $(e.currentTarget);
		if ($this.hasClass('loading')) {
			return false;
		}

		$this.addClass('loading');

		$.ajax({
			url: Routing.generate('BNSAppGPSBundle_back_place_toggle_activation'),
			type: 'POST',
			dataType: 'html',
			data: {'place_id': $this.attr('data-place-id')},
			success: function (data)
			{
				$this.toggleClass('off');
			}
		}).done(function ()
		{
			$this.removeClass('loading');
		});
	});
	
	
	
	
	if($('#gps_place_form_gps_category_id').val() != ""){
		$('.gps-choose-category[data-category-id=' + $('#gps_place_form_gps_category_id').val() + ']').find('.select').addClass('checked');
	}
	
	//Ajout d'une catégorie
	$('#gps-add-category').ajaxForm({
		beforeSubmit: function(arr, $form, options){ 
			$('.gps-category').css('opacity','0.5');
			$('#gps-add-category').hide('blind');
		},
		success: function(data){
			$('#gps-add-category-label').val('');
			$('#gps-category-list').append(data);
			$('.no-category').hide();
			$('.gps-category').css('opacity','1');
			$('#gps-add-category').show('blind');
			reloadEditForms();
		}
	});
	
	//Edition d'une catégorie
	$('.gps-category-edit').live('click',function(){
		$('.gps-category-edit-form').hide();
		$('.gps-category-informations').show();
		$('.in_edition').removeClass('in_edition');
		var id = $(this).parents('.gps-category').attr('id');
		var params = id.split('-');
		var category_id = params[2];
		$('#gps-category-' + category_id).children('.gps-category-informations').hide();
		$('#gps-category-edit-form-' + category_id).show();
		$('#gps-category-' + category_id).addClass('in_edition');
		//On recherge les ajax form
		reloadEditForms();
	});
	
	//Suppression d'une catégorie
	$('.gps-category-delete').live('click',function(){
		$('#delete-category-confirm').attr('href',Routing.generate('BNSAppGPSBundle_back_category_delete', {'id' : $(this).attr('data-category-id')}))
	});
	
	//Annulation de l'édition d'une catégorie'
	$('.gps-category-edit-form-cancel').live('click',function(e){
		e.preventDefault();
		var id = $(this).parents('.gps-category').attr('id');
		var params = id.split('-');
		var category_id = params[2];
		$('#gps-category-' + category_id).children('.gps-category-informations').show();
		$('#gps-category-edit-form-' + category_id).hide();
		//On recherge les ajax form
		reloadEditForms();
	});
	
	//Formulaire ajax pour l'édition d'une catégorie
	$('.gps-category-edit-form').ajaxForm({
		success: function(data){
			$('.in_edition').replaceWith(data);
		}
	});
	
	$('.gps-category-select').click(function (e)
	{
	    	showLayer();
		var $row = $(e.currentTarget),
			$parent = $row.parent().parent(),
			$checkbox = $row.find('.select');

		// Show loader
		var $loader = $parent.find('.loader');
		$loader.fadeIn('fast');
		$checkbox.toggleClass('checked');

		$.ajax({
			url: Routing.generate('BNSAppGPSBundle_back_category_select'),
			type: 'POST',
			dataType: 'html',
			data: {'state': $checkbox.hasClass('checked'), 'category_id': $row.data('category-id')},
			success: function (data)
			{
				$('#gps-places-list').html(data);
			}
		}).done(function ()
		{
		    hideLayer();
		    $loader.fadeOut('fast');
		});
		return false;
	});
	
	
	//Tri des catégories
	$( ".sortable" ).sortable({
		update: function(event,ui){
			var categories_ordered = new Array();
			var i = 0;
			$('.gps-category').each(function(){
				var params = $(this).attr('id').split('-');
				var category_id = params[2];
				categories_ordered[i] = category_id;
				i = i + 1;
			});
			$.post(
				Routing.generate('BNSAppGPSBundle_back_category_order', {}),
				{categories_ordered: categories_ordered}, 
				function complete(data)
				{
					//On ne fait rien
				}
			);
		}
	});	
	
	//Pour les lieux
	
	//Clic sur un lieu (en dehors du toggle et du see-map)
	$(".gps-place").live('click', function(event){
		event.preventDefault();
		//Nous permet de ne pas prendre en compte certains clics
		var realClass = event.srcElement.className;
		if(realClass != 'gps-place-see-map' && realClass != 'gps-place-toggle'){
			var keep = $(this).hasClass('active');
			$(".gps-place.active").toggleClass('active');
			if(!keep){
				$(this).toggleClass('active');
				var id = $(this).attr('id');
				var params = id.split('-');
				selected_place = params[2];
				
				$('.delete-gps-place').css('display','inline-block');
				$('.delete-gps-place').attr('href',Routing.generate('BNSAppGPSBundle_back_delete_place', { slug: $(this).find('.gps-place-slug').val()}));
								
				$('.edit-gps-place').css('display','inline-block');
				$('.edit-gps-place').attr('href',Routing.generate('BNSAppGPSBundle_back_edit_place', { slug: $(this).find('.gps-place-slug').val()}));
				
			}else{
				selected_place = null;
				$('.delete-gps-place').hide();
				$('.edit-gps-place').hide();
			}
		}
	});
	
	//Clic sur le lien "voir sur la carte"
	$(".gps-place-see-map").live('click', function(event){
		$('.gps-place-back-map').remove();
		var parent =  $(this).parents('.gps-place');
		var id = parent.attr('id');
		var params = id.split('-');
		place_id = params[2];
		$.post(
			Routing.generate('BNSAppGPSBundle_back_place_show_map', {}),
			{place_id: place_id}, 
			function complete(data)
			{
				parent.after(data);
			} 
		);
	});
	//Fermeture de la carte
	$(".gps-place-back-map-close").live('click', function(event){
		$('.gps-place-back-map').hide('blind');
		$('.gps-place-back-map').remove();
	});
	//Page de formulaire
	
	//Preview de la carte
	$("#gps-place-map-preview").live('click', function(event){
		if(!$(this).hasClass('disabled')){
			$('.gps-place-back-map').remove();
			var address = $('#gps_place_form_address').val();
			$.post(
				Routing.generate('BNSAppGPSBundle_back_place_show_map', {}),
				{address: address}, 
				function complete(data)
				{
					$('#gps-place-map-preview-render').html(data);
					$('#gps-place-map-preview-render').show('blind');
				} 
			);
		}
	});
	
	//Affichage du lien de preview
	$('#gps_place_form_address').keypress(function(){
		if($(this).val().length > 3){
			$('#gps-place-map-preview').removeClass('disabled');
		}else{
			$('#gps-place-map-preview').addClass('disabled');
		}
	});
		
	//Submit du formulaire
	$('#submit-gps-place').live('click', function(event){
		$('#gps-place-form').submit();
	});
	/*
	activePlaceDrag();
	activeLabelDrop();
	*/
});

function showLayer(){
	$('#gps-places-list').hide();
	$('.layer-places-loading').show();
}

function hideLayer(){
	$('#gps-places-list').show();
	$('.layer-places-loading').hide();
}

//Drag d'une ressource seule
function activePlaceDrag(){
	$('.place-drag').draggable({ 
		revert: true,
		revertDuration: 200,
		cursor: "move",
		opacity: 0.8,
		cursorAt: {top: 30, left: 150},
		distance: 35,
		helper: "original",
		zIndex: 1000,
		addClasses: true,
		refreshPositions: true,
		start: function(event){
			$(this).addClass('draggable-white');
		},
		stop: function(event){
			$(this).removeClass('draggable-white');
		}
	});
}

//Drop dans les labels
function activeLabelDrop(){
	$('.gps-category-drop').droppable({
		accept: ".place-drag",
		activeClass: "droppable",
		hoverClass: "drop-hover",

		drop: function(event,ui){
			//Détection des ids en jeu
			showLayer();
			var placeId = ui.draggable.attr('data-id');
			var categoryId = $(this).attr('data-id');
			$.post(
				Routing.generate('BNSAppGPSBundle_back_move_place', {}),
				{ 'categoryId' : categoryId , 'placeId' : placeId }, 
				function complete(data)
				{					
					$('#gps-places-list').html(data);
					activePlaceDrag();
					hideLayer();
				}
			);
		},
		over: function(event){
			
		},
		out: function(event){
			
		}
	});
}

//Rechargement des formulaires ajax
function reloadEditForms(){
	$('.gps-category-edit-form').ajaxForm({
		beforeSubmit: function(arr, $form, options){ 
			$('.in_edition').hide('blind');
		},
		success: function(data){
			$('.in_edition').replaceWith(data);
			$('.in_edition').show('blind');
			activeLabelDrop();
		}
	});
}