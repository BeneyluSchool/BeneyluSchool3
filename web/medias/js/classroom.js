$( ".toggle-add-team" ).click(function() {
	$( ".team-add-form" ).fadeToggle("show", "linear");
});

$(".teach-content, .student-content").click(function() {
	if ($(this).attr('class') == 'teach-content')
	{
		$('.list-teacher').fadeToggle("show", "linear");
	}
	else
	{
		$('.list-student').fadeToggle("show", "linear")
	}
});

function dragStart(event) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData("Text", event.target.getAttribute('id'));
}

function dragOver(event) {
    return false;
}

function drop(event) {
    var userId = event.dataTransfer.getData("Text");
    var teamSlug = event.target.getAttribute('id');
	if ( $( "#"+teamSlug ).hasClass("team-loading") || $( "#"+teamSlug ).hasClass("pupil") )
	{
		event.stopPropagation();
		return false;
	}

	$( "#"+teamSlug ).addClass("team-loading");
	
	var isCopy = false;
	var user = userId;
	if (userId.indexOf("copy") != -1)
	{
		user = userId.substring(userId.indexOf("copy") + 5, userId.length);
		isCopy = true;
	}
	$.get(Routing.generate('BNSAppTeamBundle_admin_add_user', { 'slug': teamSlug, 'id': user }), 
		function(data) {
			if (data == 'false') 
			{
				
			}
			if (data == 'true') 
			{
				if (isCopy)
				{
					$( "#"+userId ).appendTo("#"+teamSlug);
					$( "#"+userId ).attr('id', teamSlug + '-copy-' + user)
				}
				else
				{
					var userCloned = $( "#"+userId ).clone();
					userCloned.attr("id", teamSlug+"-copy-"+userCloned.attr("id"));
					userCloned.addClass("copy");
					userCloned.removeClass("original");
					userCloned.appendTo("#"+teamSlug);
				}
			}
			$( "#"+teamSlug ).removeClass("team-loading");
		}
	);
	
	
    event.stopPropagation();

    return false;
}

function dropIntoTrash(event) {
    var copyUserId = event.dataTransfer.getData("Text");
	var trashId = event.target.getAttribute('id');
	if ( $( "#"+trashId ).hasClass("trash-working") || copyUserId.indexOf("copy") == -1 )
	{
		event.stopPropagation();
		return false;
	}
	
	$( "#"+trashId ).addClass("trash-working");
	var teamSlug = trashId.substring(6, trashId.length);
	var userId = copyUserId.substring(copyUserId.indexOf("copy") + 5, copyUserId.length);
	$.get(Routing.generate('BNSAppTeamBundle_admin_delete_user', { 'slug': teamSlug, 'id': userId }), 
		function(data) {
			if (data == 'false') 
			{
				
			}
			if (data == 'true') 
			{
				$( "#"+copyUserId ).remove();
			}
			$( "#"+trashId ).removeClass("trash-working");
		}
	);
	
	
    event.stopPropagation();

    return false;
}
