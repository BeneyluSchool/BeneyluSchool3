$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur la messagerie interne de la Beneylu School.</p> <p>Ce module va te permettre d'envoyer des messages à tes camarades de classe ou à ton professeur.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "La messagerie",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".content-sidebar-messaging",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Retrouve facilement ton message en cliquant sur l'une des catégories suivantes.</p>",
		id: "second",
		next: "third",
		position: 3,
		title: "Filtre tes messages",
		xButton: true,
		offset: {
			top: 0,
			left: -10
		}
	});
	
	guiders.createGuider({
		attachTo:"#messaging-top-search-input",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Tu peux rechercher un message en saisissant dans ce champ de texte le ou les mots qui sont dans le message.</p>",
		id: "third",
		next: "fourth",
		position: 7,
		title: "Rechercher un message",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:"#messaging-top-new-message",
		buttons: [{name: "Je clique sur ce bouton", classString: "btn btn-success btn-small pull-right btn-simulate-click"}],
		description: "<p>Clique sur ce bouton pour écrire un nouveau message.</p>",
		id: "fourth",
		next: "fifth",
		position: 7,
		title: "Ecrire un message",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:"#messaging-write-new-msg-choose",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Choisis à qui s'adresse ton message en cliquant sur ce bouton.</p>",
		id: "fifth",
		next: "sixth",
		position: 9,
		title: "Ajouter des destinataires",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:"#messaging-top-draft",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Si tu cliques sur ce bouton, tu enregistres ton message parmi les brouillons. Tu peux le retrouver plus tard pour le finir.</p>",
		id: "sixth",
		next: "finally",
		position: 5,
		title: "Enregistrer en tant que brouillon",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo: "#messaging-top-send",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Une fois que tu auras fini d'écrire ton message, clique sur ce bouton pour l'envoyer.</p>",
		id: "finally",
		position: 5,
		title: "Envoyer le message",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
		
	$('.btn-simulate-click').live('click', function() {
		$(this).addClass('disabled');
		$('#messaging-top-new-message').trigger('click');
		setTimeout("waitingForAjaxLoad()", 500);
	});
});

function waitingForAjaxLoad() {
	if($.active == 0)
	{
	    guiders.hideAll();
	    guiders.show('fifth');
	}	
	else
	{
	    setTimeout("waitingForAjaxLoad()", 500);
	}
}
