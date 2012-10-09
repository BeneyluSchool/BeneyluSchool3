function initUserButtonStatus()
{
    $(".modal-user-container button.modal-btn-remove-user").each(function() {
        $("div.tabbable ." + extractUserClassFromButtonClasses($(this).attr("class"))).each(function() {
            $(this).removeClass("btn-success modal-btn-add-user");
            $(this).addClass("disabled");
        });
    });
}

// USER PICKER MODAL : ADD USER
$(".modal-btn-add-user").live("click", function() {
   var currentUserNewButton = $(this).clone();
   currentUserNewButton.removeClass("btn-success modal-btn-add-user");   
   currentUserNewButton.addClass("btn-danger modal-btn-remove-user");
   var modalUserContainer = $(".modal-user-container");
   modalUserContainer.find("p").hide();
   modalUserContainer.append(currentUserNewButton);
   
   disableUserFromSelection($(this).attr('class'));
});

function disableUserFromSelection(buttonClasses)
{
    $("div.tabbable ." + extractUserClassFromButtonClasses(buttonClasses)).each(function() {
        $(this).removeClass("btn-success modal-btn-add-user");
        $(this).addClass("disabled");
    });
}

// USER PICKER MODAL : REMOVE USER
$(".modal-btn-remove-user").live("click", function() {
    var buttonClasses = $(this).attr('class');
    $(this).remove();
    var modalUserContainer = $(".modal-user-container");
    if (modalUserContainer.find(".modal-btn-remove-user").length == 0)
    {
        modalUserContainer.find("p").show();
    }
   
   enableUserFromSelection(buttonClasses);
});

function enableUserFromSelection(buttonClasses)
{
    $("div.tabbable ." + extractUserClassFromButtonClasses(buttonClasses)).each(function() {
        $(this).removeClass("btn-danger disabled modal-btn-remove-user");
        $(this).addClass("btn-success modal-btn-add-user");
    });
}

// COMMON FUNCTION
function extractUserClassFromButtonClasses(buttonClasses)
{
    var userClass = buttonClasses.substring(buttonClasses.indexOf("user-"), buttonClasses.length);
    userClass = userClass.substring(0, userClass.indexOf(" "));
    
    return userClass;
}

function getSelectedUserIds()
{
    var modalUserContainer = $(".modal-user-container");
    var userIds = new Array();
    modalUserContainer.find(".modal-btn-remove-user").each(function() {
        var userClass = extractUserClassFromButtonClasses($(this).attr("class"));
        userIds.push(userClass.substring(userClass.indexOf("-") + 1, userClass.length));
    });
    
    return userIds;
}
