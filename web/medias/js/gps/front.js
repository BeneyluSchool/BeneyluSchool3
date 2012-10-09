//Info window en cours d'affichage
var visibleInfoWindow = null;

$(function(){ 		
	addListeners();

    $('#map_canvas').addClass('');

    $('#map_canvas').css('width', '100%');

    adjustHeight();

    $(window).resize(function() {
        adjustHeight();
    });
});

function adjustHeight(){
    $('#map_canvas').css('height',$(window).height() - 250);
}

//Place des écouteurs sur les marqueurs de la carte
function addListeners(){ 
	$('.place').each(function(){
		var id = $(this).attr('id');
		google.maps.event.addListener(
			window['marker_' + id],
			'click',
			function(){
				if (visibleInfoWindow) {
					visibleInfoWindow.close();
				}
				window['info_window_' + id].open(BNSMap, window['marker_' + id]);
				visibleInfoWindow = window['info_window_' + id];
				//Si on veut ajouter un comportement lors du clic dans la carte c'est ici
			}
		);
	});
}

//Clic sur un lieu dans la colonne
$('.place').live("click", function(){
	$(this).parent().parent().parent().find('.category').trigger('click');
	$('.place').removeClass('active');
	$(this).addClass('active');
	clearInfoWindows();
	google.maps.event.trigger(window['marker_' + $(this).attr('id')],'click');
});

//Clic sur une catégorie dans la colonne
$('.category').live("click", function(){
	$('.category').removeClass('active');
	$(this).addClass('active');
	var myActive = $(this).children('.active');
	$('.place').removeClass('active');
	myActive.addClass('active');
	clearMarkers();
	clearInfoWindows();
	$(this).parent().find('.place').each(function(){
		window['marker_' + $(this).attr('id')].setVisible(true);
	});
});

//Reset des infoWindows
function clearInfoWindows() {
	$('.place').each(function(){
		window['info_window_' + $(this).attr('id')].close();
	});
}

//Reset des marqueurs
function clearMarkers() {
	$('.place').each(function(){
		window['marker_' + $(this).attr('id')].setVisible(false);
	});
}

//Changement du type de carte
$('.map_type_map').live("click", function(event){
	event.preventDefault();
	BNSMap.getStreetView().setVisible(false);
	if($(this).attr('id') == "HYBRID"){
		BNSMap.setMapTypeId(google.maps.MapTypeId.HYBRID);
		$('.map_type_map').removeClass('active');
		$(this).addClass('active');
	}
	if($(this).attr('id') == "ROADMAP"){
		BNSMap.setMapTypeId(google.maps.MapTypeId.ROADMAP);
		$('.map_type_map').removeClass('active');
		$(this).addClass('active');
	}
	if($(this).attr('id') == "SATELLITE"){
		BNSMap.setMapTypeId(google.maps.MapTypeId.SATELLITE);
		$('.map_type_map').removeClass('active');
		$(this).addClass('active');
	}
	if($(this).attr('id') == "TERRAIN"){
		BNSMap.setMapTypeId(google.maps.MapTypeId.TERRAIN);
		$('.map_type_map').removeClass('active');
		$(this).addClass('active');
	}
});

//Recherche

function search(){
	event.preventDefault();
	clearInfoWindows();
	clearMarkers();
	$('.category').removeClass('active');
	$('.place').removeClass('active');
	codeAddress();	
}

$('#search-submit').live('click',function(event){
	search();
});

$("#input-address").keypress(function(event) {
  if ( event.which == 13 ) {
     search();
  }
});


$('#search-cancel').live('click',function(event){
	event.preventDefault();
	$('#input-address').val('');
	marker_search.setVisible(false);
});

var geocoder = new google.maps.Geocoder();
var marker_search;
function codeAddress() {
	var address = $('#input-address').val();
	geocoder.geocode( { 'address': address}, function(results, status) {
		if (status == google.maps.GeocoderStatus.OK) {
			BNSMap.setCenter(results[0].geometry.location);
			marker_search = new google.maps.Marker({
				map: BNSMap, 
				position: results[0].geometry.location,
				icon: '/medias/images/gps/search.png'
			});
		}else{
			//Si STATUS = ZERO_RESULTS => pas de résultat
			$('#input-address').val('Pas de résultats');
			$('#input-address').css('color','red');
			
			setTimeout(function(){ $('#input-address').val(''); $('#input-address').css('color',''); },3000);
		}
	});
}


