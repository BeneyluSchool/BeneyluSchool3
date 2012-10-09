google.load('search', '1');
google.setOnLoadCallback(OnLoad);
var currentSearch = "";
	
function OnLoad() {
	
	var options = {};
	
	options['enableImageSearch'] = true;
	
	var customSearchControl = new google.search.CustomSearchControl({'crefUrl' : crefUrl},options);
	
	// Récupération des objets
	var webSearcher = customSearchControl.getWebSearcher();

	var imageSearcher = customSearchControl.getImageSearcher();
	// Fin Récupération

	// Layout des images
	imageSearcher.setLayout(google.search.ImageSearch.LAYOUT_POPUP);
	// Fin layout

		
	// Fin SAFE_SEARCH

	// Call back pour call ajax au retour du résultat
	customSearchControl.setSearchCompleteCallback(this, function(control, searcher, query) {
		
		$.ajax({
			url: Routing.generate('BNSAppResourceBundle_search_add',  {label: currentSearch }),
			success: function() {
				return false;
			}
		});
	});
	// Fin callback
	
	//Bouton : recherche au clic
	$('#search-google-submit').live('click',function(){
		var val = $('#search-input').val();
		currentSearch = val;
		if(val.length > 0){
			
			$('#resource-search-alert').hide();
			//Cache du doodle
			$('.toolbar-quota').hide();
			$('#resource-doodle:visible').hide('blind',null,900);
			$('#toolbar-search').show();

			$('#search-content-form').remove();
			$('#search-input-toolbar').val(val);
			
			//Exécution de la recherche
			customSearchControl.execute(val);
			$("#search-results").show();
		}else{
			$('#resource-search-alert').show();
		}
	});
	
	//Bouton : recherche au clic
	$('#search-google-submit-toolbar').live('click',function(){
		$("#resources").empty();
		var val = $('#search-input-toolbar').val();
		currentSearch = val;
		if(val.length > 0){
			//Exécution de la recherche
			customSearchControl.execute(val);
			$("#search-results").show();
		}else{
			$('#resource-search-alert').show();
		}
	});
	
	//On ne dessine que les résultats
	var drawOptions = new google.search.DrawOptions();
	drawOptions.enableSearchResultsOnly();
	drawOptions.setAutoComplete(true);
	// C'est parti
	customSearchControl.draw('search-results',drawOptions);
}