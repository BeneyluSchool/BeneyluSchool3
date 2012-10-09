$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur le module Blog.</p> <p>Ce module vous permet de consulter tous les articles publiés par les élèves et les enseignants de la classe.</p>",
		id: "first",
		next: "finally",
		overlay: true,
		title: "Le Blog",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo: ".blog-header",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Basculez en mode administration du blog puis rendez vous sur la page \"Personnalisation\" pour changer le titre, la description ou encore l'avatar du blog.</p>",
		id: "finally",
		position: 7,
		title: "Personnaliser le blog",
		xButton: true,
		offset: {
			top: 20,
			left: 100
		}
	});
});
