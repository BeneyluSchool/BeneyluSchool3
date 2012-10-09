$(function(){ 

	//Clic dans la navigation : rechargement de sidebar + contenu en fonction de l'id du label
	$('.resource-nav').live("click", function(){
		$('#garbage-empty').hide();
		prepareLoading();
		var id = $(this).attr('id');
				
		//Sinon c'est un clic sur les favoris
		if($(this).hasClass('favorite')){
			$('.container-sidebar-resource .active').removeClass("active");
			$(this).addClass("active");
			//Mise à jour contenu
			endSidebarLoading();
			$.ajax({
				url: Routing.generate('BNSAppResourceBundle_front_navigation_content',  {"page": '0',"type": "favoris"}),
				success: function(data) {
					$("#resource-navigation").html(data);	
					$('.container-block').addClass('resource-list-bg');
					endNavigationLoading();
				}
			});	
			
		}else if($(this).hasClass('garbage')){
			$('.container-sidebar-resource .active').removeClass("active");
			$(this).addClass("active");
			endSidebarLoading();
			//Mise à jour contenu
			$.ajax({
				url: Routing.generate('BNSAppResourceBundle_front_navigation_content',  {"page": '0',"type": "garbage"}),
				success: function(data) {
					$("#resource-navigation").html(data);
					$('.container-block').addClass('resource-list-bg');
					endNavigationLoading();
				}
			});	
			
		}else{
			//Si Id on a cliqué sur un label
			
			var params = id.split('-');
			var type = params[0];
			var label_id = params[1];

			//Mise à jour sidebar
			$.post(
				Routing.generate('BNSAppResourceBundle_front_navigation_sidebar', {}),
				{type: type, label_id: label_id}, 
				function success(data)
				{
					//Sidebar + mise en session du currentLabel
					$("#resource-sidebar").html(data);
					endSidebarLoading();
					//Mise à jour contenu
					$.post(
						Routing.generate('BNSAppResourceBundle_front_navigation_content', {"page": 0,"type": "ressources"}),
						function success(data)
						{
							$('.container-block').addClass('resource-list-bg');
							$("#resource-navigation").html(data);
							endNavigationLoading();
							
						}
					);
				}
			);
		}
	});
	
		
	//Vue d'une ressource
	$('.resource-view').live('click',function(){ 
		//On ne prend pas en compte si clic sur bouton de "selection" (favori / selection)"
		if(!$('#' + event.srcElement.id).hasClass('selection-button')){
			var id = $(this).attr('id');
			var params = id.split('-');
			var resource_id = params[1];
			//Mise à jour contenu
			$.post(
				Routing.generate('BNSAppResourceBundle_front_navigation_content_resource', {}),
				{resource_id: resource_id },
				function complete(data)
				{
					$("#resource-navigation").hide();
					$("#resource-current").html(data);
					$("#resource-current").show();
				}
			)
		}
	});
	
	//Selection d'une ressource
	$('.resource-select').live('click',function(event){ 
		var id = $(this).attr('id');
		var params = id.split('-');
		var resource_id = params[0];
		//type = add ou delete
		var type = params[1];		
		//Mise à jour contenu
		if(type == "add")
			$(this).toggleClass('active');
		else
			$('#' + resource_id + '-add').removeClass('active');
		
		$("#resource-selection").hide();
		$("#resource-navigation-loading").show();
		$(".list-resources").hide();
		$.post(
			Routing.generate('BNSAppResourceBundle_front_selection', {}),
			{resource_id: resource_id}, 
			function complete(data)
			{			
				$("#resource-" + resource_id).replaceWith(data);
				//Mise à jour du panier de selection
				$.ajax({
					url: Routing.generate('BNSAppResourceBundle_front_selection_view', {}),
					success: function(data) {
						$("#resource-selection").html(data);
						$("#resource-navigation-loading").hide();
						$("#resource-selection").show();
						$(".list-resources").show();
					}
				});
			}
		);
		
	});
	
	//Toggle favori
	$('.resource-favorite').live('click',function(){ 
		var id = $(this).attr('id');
		$(this).toggleClass('active');
		$("#resource-navigation-loading").show();
		$("#resource-navigation").hide();
		$.post(
			Routing.generate('BNSAppResourceBundle_front_navigation_favorite', {}),
			{resource_id: id}, 
			function complete(data)
			{
				$.ajax({
					url: Routing.generate('BNSAppResourceBundle_front_navigation_content', {}),
					success: function(data) {
						$("#resource-navigation").html(data);
						$("#resource-navigation-loading").hide();
						$("#resource-navigation").show();
					}
				});
			});
	});
	//Selection d'un filtre
	$('.resource-filter-column > a').live('click',function(){ 	
		var type = $(this).attr('id');
		prepareLoading();
		$.post(
			Routing.generate('BNSAppResourceBundle_front_navigation_filter_type', {}),
			{type: type}, 
			function complete(data)
			{
				$('#filter-type').html(data);
				$.ajax({
					url: Routing.generate('BNSAppResourceBundle_front_navigation_content', {}),
					success: function(data) {
						$("#resource-navigation").html(data);
						endLoading();
					}
				});
			}
		);
	});
	//Clic dans la pagination des ressources
	$('.resource-pagination').live('click',function(){
		var id = $(this).attr('id');
		var params = id.split('-');
		var page = params[2];
		var type = params[1];
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_navigation_content',  {"page": page,"type": type}),
			success: function(data) {
				$("#resource-navigation").html(data);
			}
		});
	});	
	
	//Bouton de retour à la navigation
	$('.resource-content').on('click','.resource-return',function(){
		$("#resource-navigation").show();
		$("#resource-current").empty();
	});	
	
	//Bouton d'édition d'une ressource
	$('.resource-edit').live('click',function(){
		var id = $(this).attr('id');
		var params = id.split('-');
		var resource_id = params[2];
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_navigation_content_resource_edit', { resource_id: resource_id }),
			success: function(data)
			{
				$('#resource-current').html(data);
			}
		});
	});
		
	//Suppression d'un label à une resource
	$('.label.delete').live('click',function(){
		var params = $(this).parent('.label-text').attr('id').split('_');
		var type = params[0];
		var label_id = params[2];
		$.post(
			Routing.generate('BNSAppResourceBundle_front_navigation_content_resource_delete_label', {}),
			{ 'resource_id' : $('#resource_id').val() , 'label_id' : label_id, 'type': type },
			function complete(data)
			{					
				$(".labels-list").html(data);
			}
		);		
	});

	//////////////    Boutons d'actions sur selection    \\\\\\\\\\\\\\\\\\\
	
	//Bouton d'insertion d'une sélection
	$('.resource-selection-insert').live('click',function(){
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_insert'),
			success: function(data) {
				parent.ed.focus();
				parent.ed.selection.setContent(data);
				closeMainIframe();
			}
		});
	});
	
	//Bouton de join d'une selection
	$('.resource-selection-join').live('click',function(){
		var ref = $(this).attr('data-reference');
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_join'),
			success: function(data) {
				$('#' + ref,window.parent.document).prepend(data);
				closeMainIframe();
			}
		});
	});
	
	//Bouton de select d'une ressource
	$('.resource-selection-select').live('click',function(){
		var final_id = $(this).attr('data-final-id');
		$('#' + final_id, window.parent.document).trigger('change');
		var callback = $(this).attr('data-callback');
		var resource_id = $(this).attr('data-resource-id');
		$.post(
			Routing.generate('BNSAppResourceBundle_front_selection_select', {}),
			{ resource_id: resource_id },
			function complete(data){
				$('#' + final_id,window.parent.document).val(resource_id);
				$('#' + callback,window.parent.document).html(data);
				closeMainIframe();				
			}
		);
	});
	
	//Bouton de "Vidage" d'une sélection
	$('.resource-selection-empty').live('click',function(){
		$("#resource-navigation-loading").show();
		$("#resource-navigation").hide();
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_empty'),
			success: function(data) {
				$("#resource-navigation-loading").hide();
				$("#resource-navigation").show();
				$("#resource-navigation").html(data);
			}
		});
	});
	
	//Bouton de "Suppression" d'une sélection
	$('.resource-selection-delete-confirm').live('click',function(){
		$('#resourceSelectionDelete').modal('hide');
		$('#resourceGarbageSelectionDeleteForever').modal('hide');
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_delete'),
			success: function(data) {
				//Vidage de la selection
				$('#resource-selection').empty();
				//Rechargement des resources en cours
				$.ajax({
					url: Routing.generate('BNSAppResourceBundle_front_navigation_content', {}),
					success: function(data) {
						$("#resource-navigation").html(data);
					}
				});
			}
		});
	});
	
	//Bouton de "Restauration" d'une sélection
	$('#garbage-restore').live('click',function(){
		prepareLoading();
		endSidebarLoading();
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_restore'),
			success: function(data) {
				//Vidage de la selection
				$('#resource-selection').empty();
				//Rechargement des resources en cours
				$.ajax({
					url: Routing.generate('BNSAppResourceBundle_front_navigation_content', {}),
					success: function(data) {
						$("#resource-navigation").html(data);
						endNavigationLoading();
					}
				});
			}
		});
	});
	
	//Bouton de mise en favori de la selection
	$('.resource-selection-add-to-favorite').live('click',function(){
		var singleFile = $(this).hasClass('single-file');
		$("#resource-navigation-loading").show();
		if(singleFile)
			$("#resource-current").hide();
		else
			$("#resource-navigation").hide();
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_add_to_favorite',{"singleFile": singleFile}),
			success: function(data) {
				$("#resource-navigation-loading").hide();
				if(singleFile){
					$("#resource-current").html(data);
					$('#resource-current').show();
				}else{
					$("#resource-navigation").html(data);
					$("#resource-navigation").show();
				}	
			}
		});
	});
	
	
	
	//////////////////    Ajout de label dans la sidebar   \\\\\\\\\\\\\\\\\\\\\\\\\
	
	//Clic sur le bouton d'ajout
	$('#resource-sidebar').on('click',"#resource-sidebar-new-label-btn",function(){
		 //$('#resource-sidebar-new-label-btn').hide();
		 //$('#resource-sidebar-new-label-form').show();
	});
	
	//Clic sur Annuler
	$('#resource-sidebar').on('click',"#resource-sidebar-new-label-cancel",function(){
		 /*$('#resource-sidebar-new-label-input').val("");
		 $('#resource-sidebar-new-label-form').hide();
		 $('#resource-sidebar-new-label-btn').show();*/
	});
	
	//Clic sur le submit
	$('#resourceAddLabel').on('click',"#resource-sidebar-new-label-submit",function(){
		$('#resource-sidebar-new-label-submit').addClass('disabled');
		var value = $('#resource-sidebar-new-label-input').val();
		$('#resourceAddLabel').find('.add-label-form').hide();
		$('#resourceAddLabel').find('.loader').show();
		$.post(
			Routing.generate('BNSAppResourceBundle_label_add_front_submit', {}),
			{value: value}, 
			function complete(data)
			{
				$.ajax({
					url: Routing.generate('BNSAppResourceBundle_front_navigation_sidebar', {}),
					success: function(data)
					{
						
						$('#resource-sidebar-new-label-input').val('');
						$("#resource-sidebar").html(data);
						$('#resourceAddLabel').modal('hide');
						$('#resourceAddLabel').find('.add-label-form').show();
						$('#resourceAddLabel').find('.loader').hide();
						$('#resource-sidebar-new-label-submit').removeClass('disabled');
					}
				});
			}
		);
	});
	
	//Ajustement de la hauteur
	adjustHeight();		
	
	$(window).resize(function() {
		adjustHeight();
	});
	
	
	
	/////////////////////////    Suppression \\\\\\\\\\\\\\\\\\\\\\\\\\\
	
	$('.resource-selection-delete').live('click',function(){
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_selection_delete_alert', {}),
			success: function(data)
			{
				$('#resource-selection-delete-content').html(data);
			}
		});
	});
	
	$('#resource-delete-preview-cancel').live('click',function(){
		$('#resourceSelectionDelete').modal('hide');
	});
	
	
	$('#resource-garbage-empty-confirm').live('click',function(){
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_front_garbage_empty', {}),
			success: function(data)
			{
				$('#resourceGarbageEmpty').modal('hide');
				$("#resource-navigation").html(data);
			}
		});
	});
	
	

	/////////////////////////    Recherche   \\\\\\\\\\\\\\\\\\\\\\\\\\\\



	//Recherche page d'accueil
	$('#search-resource-submit').live('click',function(){
		if($('#search-input').val().length > 0){
			var val = $('#search-input').val();
			$('#resource-search-alert').hide();
			//On cache le doodle
			$('.toolbar-quota').hide();
			$('#resource-doodle:visible').hide('blind',null,900);
			$('#toolbar-search').show();

			$('#search-content-form').remove();
			$('#search-input-toolbar').val(val);

			//Exécution de la recherche
			search(val);
		}else{
			$('#resource-search-alert').show();
		}
	});

	//Recherche toolbar
	$('#search-resource-submit-toolbar').live('click',function(){
		var val = $('#search-input-toolbar').val();
		search(val);
	});
	
	
});

//fonctions à sortir du function()

//Ajustement de la hauteur
function adjustHeight()
{
	$('.main-content').each(function(){
		$(this).height($(window).height() - $(this).offset().top - parseInt($(this).css('paddingBottom')) - parseInt($(this).css('marginBottom')) - parseInt($(this).css('paddingTop')) + 'px');
	});
}

function closeMainIframe(){
	$('#resource-iframe',window.parent.document).hide(1000, function () {
		$('#resource-iframe',window.parent.document).remove();
	});
}

//Affichage des blocks ressources
function search(val){
	prepareLoading();
	endSidebarLoading();
	$.post(
		Routing.generate('BNSAppResourceBundle_front_navigation_content', {'type' : 'recherche','page': 0}),
		{ 'q' : val },
		function complete(data)
		{					
			$("#search-results").hide();
			var internet = $("#search-results").get(0);
			$("#resource-navigation").html(data);
			$("#resource-navigation").prepend(internet);
			$('.container-block').addClass('resource-list-bg');
			endNavigationLoading();
		}
	);
}

/////////////////    Drag & Drop des ressources    \\\\\\\\\\\\\\\\\\\\

//Drag d'une ressource seule
function activeResourceDrag(){
	$('.resource-drag').draggable({ 
		revert: true,
		revertDuration: 200,
		cursor: "move",
		opacity: 0.7,		
		cursorAt: {top: 50, left: 50},
		distance: 35,
		helper: "clone",
		stop: function(event){
			$('.resource-nav-hidden').hide();
		}
	});
}

//Drag d'une sélection de ressources
function activeResourceSelectionDrag(){
	$('#resource-multiple-move').draggable({ 
		revert: true,
		revertDuration: 200,
		cursor: "move",
		cursorAt: {top: 10, left: -20},
		distance: 30,
		helper: function( event ) {
			var nbRes = $('.resource-selected').length;
			return $( "<div class='alert alert-success'><strong>" + nbRes + " Ressources selectionnées</strong></div>" );
		},
		refreshPositions: true,
		stop: function(event){
			$('.resource-nav-hidden').hide();
		}
	});
}

//Drop dans les labels
function activeLabelDrop(){
	$('.resource-nav').droppable({
		accept: ".resource-drag",
		activeClass: "ui-state-hover",
		hoverClass: "drop",

		drop: function(event,ui){
			//Declenchement de l'appel
			var dragType = ui.draggable.attr('id');

			$("#resource-navigation").hide();
			$("#resource-navigation-loading").show();

			if(dragType != "resource-multiple-move")
			{   // 1 - Fichier unique
				var resourceId = ui.draggable.attr('id');
				var params = resourceId.split('-');
				resourceId = params[1];
				var labelId = $(this).attr('id');
				params = labelId.split('-');
				labelId = params[1];
				var labelType = params[0];
				$.post(
					Routing.generate('BNSAppResourceBundle_front_navigation_move_resource', {}),
					{ 'resource_id' : resourceId , 'label_id' : labelId, 'type': labelType }, 
					function complete(data)
					{					
						$.ajax({
							url: Routing.generate('BNSAppResourceBundle_front_navigation_content', {}),
							success: function(data) {
								$("#resource-navigation").html(data);
								$("#resource-navigation-loading").hide();
								$("#resource-navigation").show();
							}
						});
					}
				);
			}else
			{	// 2 - Fichiers multiples
				var labelId = $(this).attr('id');
				params = labelId.split('-');
				labelId = params[1];
				var labelType = params[0];
				$.post(
					Routing.generate('BNSAppResourceBundle_front_selection_move', {}),
					{ 'label_id' : labelId, 'type': labelType }, 
					function complete(data)
					{					
						$.ajax({
							url: Routing.generate('BNSAppResourceBundle_front_navigation_content', {}),
							success: function(data) {
								$("#resource-navigation").html(data);
								$("#resource-navigation-loading").hide();
								$("#resource-navigation").show();
							}
						});
					}
				);
			}
		},
		over: function(event){
			//Affichage des enfants
			//Id de l'élement
			var id = $(this).attr('id');
			if(id != null){
				var params = id.split('-');
				var labelId = params[1];
				$('#' + labelId + '-children').children('.resource-nav-hidden').toggle();
			}
		},
		out: function(event){
			//Affichage des enfants
			//Id de l'élement
			/*var id = $(this).attr('id');
			var params = id.split('-');
			var labelId = params[1];*/
		}
	});
}

//Affichage des layer de loading
function prepareLoading(){
	//Suppression de la ressource en cours si il y en a une
	$("#resource-current").empty();

	$("#resource-sidebar").hide();
	$("#resource-navigation").hide();

	$("#resource-navigation-loading").show();
	$("#resource-sidebar-loading").show();
}
//Cachage des layers de loading
function endLoading(){
	endSidebarLoading();
	endNavigationLoading();
}

function endSidebarLoading(){
	$("#resource-sidebar-loading").hide();
	$("#resource-sidebar").show();
}
function endNavigationLoading(){
	$("#resource-navigation-loading").hide();
	$("#resource-navigation").show();
	setTimeout(adjustHeight(),500);
}