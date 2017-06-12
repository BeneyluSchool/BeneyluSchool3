google.load('search', '1');
var currentSearch = '';

function doSearch(searchQuery)
{
	// Bouton : recherche au clic
	currentSearch = searchQuery;

	if (searchQuery.length > 0) {
		//Exécution de la recherche
		var customSearchControl = new google.search.CustomSearchControl({'crefUrl' : crefUrl}, {'enableImageSearch': true});

		// Récupération des objets
		var imageSearcher = customSearchControl.getImageSearcher();
		// Fin Récupération

		// Layout des images
		imageSearcher.setLayout(google.search.ImageSearch.LAYOUT_POPUP);
		// Fin layout

		// Bouton : recherche au clic

		// On ne dessine que les résultats
		var drawOptions = new google.search.DrawOptions();
		drawOptions.enableSearchResultsOnly();
		drawOptions.setAutoComplete(true);
		
		// Finally
		customSearchControl.draw('search-results', drawOptions);
		
		customSearchControl.execute(searchQuery);
	}
}