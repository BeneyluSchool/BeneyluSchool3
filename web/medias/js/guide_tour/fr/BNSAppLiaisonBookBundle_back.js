$(document).ready(function() {
	var $firstStepButton = [{name: "Ne plus afficher ce message", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }];
	if ($('.content-news').length == 0) {
		$firstStepButton = [];
	}
	
	guiders.createGuider({
		attachTo: ".bns-info",
		buttons: $firstStepButton,
		description: "<p>Cliquez sur ce bouton pour écrire un nouveau message qui apparaîtra sur le carnet de liaison.</p>",
		id: "first",
		position: 7,
		title: "Ecrire un message",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
});
