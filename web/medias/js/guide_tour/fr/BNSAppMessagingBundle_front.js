$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur la messagerie interne de la Beneylu School.</p> <p>Ce module vous permet d'échanger des messages privés avec les autres utilisateurs de votre classe.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "La messagerie",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".content-sidebar-messaging",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Retrouvez facilement votre message en cliquant sur l'une des catégories suivantes.</p>",
		id: "second",
		next: "third",
		position: 3,
		title: "Filtrer les messages",
		xButton: true,
		offset: {
			top: 0,
			left: -10
		}
	});
	
	guiders.createGuider({
		attachTo:"#messaging-top-search-input",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Recherchez un message en saisissant un ou plusieurs mots qui se trouve dans l'objet ou le corps du message.</p>",
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
		buttons: [{name: "Simuler un clic sur ce bouton", classString: "btn btn-success btn-small pull-right btn-simulate-click"}],
		description: "<p>Cliquez sur ce bouton pour écrire et envoyer un nouveau message.</p>",
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
		description: "<p>Cliquez sur ce bouton et choisissez le ou les destinataires du message.</p>",
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
		description: "<p>Vous pouvez enregistrer un message en tant que brouillon pour continuer sa rédaction plus tard.</p>",
		id: "sixth",
		next: "finally",
		position: 5,
		title: "Enregistrer un brouillon",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo: "#messaging-top-send",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Une fois que vous avez fini d'écrire votre message, cliquez sur ce bouton pour l'envoyer.</p>",
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
