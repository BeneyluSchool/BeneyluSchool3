
function displaySubmitForm($form)
{
    var $oneOfRadioButtonChecked = false;
    $form.find('input[type=radio]').each(function() {
        if ($(this).is(':checked')) {
            $oneOfRadioButtonChecked = true;
        }
    });

    if ($oneOfRadioButtonChecked && '' != $form.find('input[type=file]').val()) {
        $('button.btn-confirm-import-pupil').fadeIn('slow');
    }
}

$(function(){


    // set your twitter id
    var user = 'beneyluschool';

    jQuery(function($){$.datepicker.setDefaults($.datepicker.regional['fr']);});

    // Afficher le formulaire pour écrire un message d'accueil pour la classe
    $(".write-home-message").click(function(event) {
        event.preventDefault();
        if ($(this).hasClass('disabled')) {
            return false;
        }

        $('div.write-home-message-container').slideDown('slow');
        $(this).addClass('disabled');
    });

    $('button.cancel-write-home-message').click(function(event) {
        event.preventDefault();
        $('div.write-home-message-container').slideUp('slow');
        $('.write-home-message').removeClass('disabled');
    });

    // Activation/désactivation d'un module
    $("span.btn-change-module-state").live('click', function()
    {
        if ($('.content-dashboard-connexion-management').hasClass('loading')) {
            return false;
        }

        var moduleUniqueName = $(this).attr('data-module-unique-name'),
            roleId = $(this).attr('data-role-id'),
            groupId = $(this).attr('data-group-id'),
            currentState = ($(this).hasClass('active')? 1 : 0);

        $('.content-dashboard-connexion-management').addClass('loading');
        $(this).addClass('loading');

        $.ajax({
            url: Routing.generate('BNSAppMainBundle_module_activation_toggle'),
            data: {
                groupId: groupId,
                moduleUniqueName: moduleUniqueName,
                roleId: roleId,
                currentState: currentState
            },
            success: function(data)
            {
                $('#' + roleId + '-' + groupId + '-' + moduleUniqueName).replaceWith(data);
            }
        }).done(function ()
        {
            $('.content-dashboard-connexion-management').removeClass('loading');
        });
    });

    // Listener du clic sur le bouton "J'ai terminé" de la page d'import des élèves au moyen de fichier csv
    $('button.btn-confirm-import-pupil').click(function() {
        $('form#import-pupil-form').submit();
    });

    $('form#import-pupil-form').change(function() {
        displaySubmitForm($(this));
    });

    $('.submit-custom-classroom-form').click(function() {
        $('#custom-classroom-form').submit();
    });

    $('.submit-blackboard-classroom-form').click(function() {
        $('form#submit-blackboard-classroom-form').submit();
    });

    $('.btn-save-preferences').click(function() {
        $('form#custom-classroom-form').submit();
    });



//Gère les opérations de selection des uilisateurs à partir du UserPicker
    $('.user-block.selectable').live('click',function(e) {
        e.preventDefault();
        if ($('.selected-user-container').find('a[data-user-id = ' + $(this).attr('data-user-id') + ']').length <= 3) {
            $(this).clone().addClass('cancel small').removeClass("checkbox big selectable").prependTo('.selected-user-container');
            $(this).removeClass('selectable bns-checkbox').addClass("selected is-selected");
            $('.no-selection').hide();
        }
    });

    $( ".jq-date" ).datepicker({});

    $('.header-buttons .submit-profile').live('click',function(e){
        e.preventDefault();
        var $parentsIds = new Array();
        $('.content-profile .block-users.parents').find('.bns-user.toggle').each(function()
        {
            if ($parentsIds.indexOf($(this).attr('data-user-id')) == -1) {
                $parentsIds.push($(this).attr('data-user-id'));
            }
        });

        var $assistantIds = new Array();
        $('.content-profile .block-users.assistants').find('.bns-user.toggle').each(function() {
          if ($assistantIds.indexOf($(this).attr('data-user-id')) == -1) {
            $assistantIds.push($(this).attr('data-user-id'));
          }
        });

        $('#profile_form_parentsIdsToDissociate').val($parentsIds).trigger('change');
      $('#profile_form_assistantsIdsToDissociate').val($assistantIds).trigger('change');

        $('#save-profile').submit();

    });

    $('#edit-pupil-confirm-modal .submit-profile-modal').live('click',function(e){
        e.preventDefault();

        $('#save-profile').submit();
    });

    $('.submit-partnership-form').live('click',function() {
        $('#partnership-form').submit();
    });
});
