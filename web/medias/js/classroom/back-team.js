$("a.add-group").click(function() {
	if ($(this).hasClass('disabled')) {
		return false;
	}
	
    $("div.add-group-form").slideDown('slow');
	$(this).addClass('disabled');
});

$(".create-group-btn").click(function() {
    var loadingImg = $(this).parent().find(".loading");

    var nameTextInput = $("#new-workgroup-name");

    if (nameTextInput.val().replace(/ /g, '') == '')
    {
        alert("Group's name missing!");
        nameTextInput.focus();

        return false;
    }
	
    loadingImg.show();			
    $.get(Routing.generate('BNSAppClassroomBundle_back_new_team', {'name' : nameTextInput.val()}),
        function(data)
        {
            var teamsContainer = $("#teams-container");
            if ($("#no-team").length > 0)
            {
                $("#no-team").remove();
            }

            teamsContainer.html(data + teamsContainer.html());
            $("div.add-group-form").fadeOut("slow");
            nameTextInput.val('');
            loadingImg.hide();
			$('a.add-group').removeClass('disabled');
        }
    );
});

function drag(target, event)
{
    event.dataTransfer.setData("Text", target.id);
}

function drop(target, event)
{
    var userDivId = event.dataTransfer.getData("Text");
    var userDiv = $("#" + userDivId);
    var userDivIdTab = userDivId.split("_");
    var userId = userDivIdTab[3];
    var groupSlug = target.id.substring(5, target.id.length);
    var srcGroupSlug = userDivIdTab[1];
    var currentGroupDiv = $("#"+target.id);
    var loadingImg = currentGroupDiv.parent().find($(".loading"));

    if (currentGroupDiv.find($("#team_" + groupSlug + "_user_" + userId)).length > 0)
    {
            alert("L'utilisateur est déjà dans le groupe !");

            return false;
    }

    loadingImg.show();
    $.get(Routing.generate('BNSAppClassroomBundle_back_team_remove_pupil', {'teamSlug': srcGroupSlug, 'userId': userId}),
            function(data) {
                    if (data == 'true')
                    {
                            $.get(Routing.generate('BNSAppClassroomBundle_back_team_add_pupil', {'teamSlug': groupSlug, 'userId': userId}),
                                    function(data) {
                                            if (data == 'true')
                                            {
                                                    currentGroupDiv.find("p").remove();
                                                    currentGroupDiv.append(userDiv);
                                                    userDiv.attr("id", "#team_" + groupSlug + "_user_" + userId);
                                            }

                                            loadingImg.hide();
                                    }
                            );
                    }
                    else
                    {
                            alert("L'opération de suppression a échoué !");
                            loadingImg.hide();
                    }


            }
    );	

    event.preventDefault();
}

function dropIntoTrash(target, event)
{
    var userDivId = event.dataTransfer.getData("Text");
    var userDiv = $("#" + userDivId);
    var userDivIdTab = userDivId.split("_");
    var userId = userDivIdTab[3];
    var groupSlug = target.id.substring(6, target.id.length);
    var currentGroupDiv = $("#"+target.id);
    var loadingImg = currentGroupDiv.parent().find($(".loading"));

    if (userDivIdTab[1] != groupSlug)
    {
        alert("Action invalide !");

        return false;
    }

    loadingImg.show();
    $.get(Routing.generate('BNSAppClassroomBundle_back_team_remove_pupil', {'teamSlug': groupSlug, 'userId': userId}),
        function(data) {
            if (data == 'true')
            {
                    userDiv.remove();
            }
            else
            {
                    alert("L'opération de suppression a échoué !");
            }

            loadingImg.hide();
        }
    );	

    event.preventDefault();
}

function dragStart(event) {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData("Text", event.target.getAttribute('id'));
}

function dragOver(event) {
    return false;
}

var currentTeamSlug = "";

function displayUserPicker(id, groupParentSlug, groupSlug)
{
    currentTeamSlug = groupSlug;
    var loadImg = $("#" + id).parent().find("img.loading");
    loadImg.show();
    $.post(
        Routing.generate('BNSAppMainBundle_user_picker'),
        { 
            group_context_slug: groupParentSlug,  
            current_group_slug: groupSlug
        },
        function(data)
        {
            $("#userPickerModal").html(data);
            $("#userPickerModal").modal('show');
            loadImg.hide();
        }
    );
}

// On se met en écoute sur le bouton "Confirmer" du modal UserPicker
// Effectue les traitements adéquats pour que les utilisateurs soit correctement ajoutés au groupe
$("a.btn-confirm").live("click", function() {
    var loadingImg = $("div.modal-footer").find("img.loading");
    loadingImg.show();
    userIds = getSelectedUserIds();
    if (currentTeamSlug == "")
    {
        return false;
    }
    
    $.post(
        Routing.generate('BNSAppClassroomBundle_back_team_add_remove_users'),
        { 
            team_slug: currentTeamSlug, 
            user_ids: userIds 
        },
        function(data)
        {
            var userPickerModalDiv = $("#userPickerModal");
            if (data == "true")
            {
                $.get(
                    Routing.generate('BNSAppClassroomBundle_back_team_reload_block', { 'teamSlug': currentTeamSlug }),
                    function(data)
                    {
                        var currentTeamContainer = $("#team-" + currentTeamSlug).parent().parent();
                        currentTeamContainer.after(data);
                        currentTeamContainer.remove();
                        $("#userPickerModal").html("");            
                        $("#userPickerModal").modal("hide");
                        loadingImg.hide();
                    }
                );
            }
            else
            {
                userPickerModalDiv.html("");            
                userPickerModalDiv.modal('hide');
                loadingImg.hide();
            }
            
        }
    );
});
