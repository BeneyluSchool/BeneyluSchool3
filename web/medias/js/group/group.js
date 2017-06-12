
var $createMore = false;

function addUserLastProcess($role,groupSlug)
{
    $('div.create-user-loader').hide();
    $('div.create-user-success').show();
    // L'utilisateur souhaite continuer la création de d'autres utilisateurs
    if ($createMore) {
        $.ajax({
            url: Routing.generate('BNSAppGroupBundle_group_add_user_modal_body',{'groupSlug' : groupSlug }),
            type: 'POST',
            data: {
                user_role_requested: $role
            },
            dataType: 'html',
            success: function (data)
            {
                $('div.add-user-modal-content').html(data);
                $createMore = false;
            }
        });
    }
}

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


$(function(){



    $('#group-submit-home-message').click(function(){
        $('#group-home-message').submit();
    });

    $('#group-add-user-submit').click(function(){
        $('#group-add-user-form').submit();
    });

    $('#rule-add-submit').click(function(){
        $('#rule-add-form').submit();
    });


//Fiche edition groupe
    $('.group-attribute').on('click','.edit-btn',function(){
        var id = $(this).parent().parent().attr('id');
        var groupSlug = $(this).parent().parent().attr('data-group-slug');
        var attribute_unique_name = $(this).parent().parent().attr('data-template-name');

        $.post(
            Routing.generate('BNSAppGroupBundle_group_param_form', {'groupSlug': groupSlug}),
            { 'attribute_unique_name': attribute_unique_name},
            function complete(data)
            {
                $('#' + id).html(data);
            }
        );

    });

//Fiche groupe
    $('.group-attribute').on('click','.group-attribute-form-submit',function(){
        var id = $(this).parent().attr('id');
        var groupSlug = $(this).parent().attr('data-group-slug');
        var attribute_unique_name = $(this).parent().attr('data-template-name');
        var value = $('#form-' + groupSlug + '-' + attribute_unique_name).val();
        $.post(
            Routing.generate('BNSAppGroupBundle_group_param_form', {'groupSlug': groupSlug}),
            { 'attribute_unique_name': attribute_unique_name , 'value': value},
            function complete(data)
            {
                $('#' + id).html(data);
            }
        );
    });


// Listener sur le clic des liens "Ajouter un enseignant"/"Ajouter un élève"
    $('a.add-user-button').click(function (event)
    {
        $('span.user-role-label').html($(this).attr('data-role-label'));
        $('div.add-user-modal-content').html('');
        $('div.create-user-success').hide();
        $('div.invite-user-success').hide();
        event.preventDefault();
        $.ajax({
            url: Routing.generate('BNSAppGroupBundle_group_add_user_modal_body', {'groupSlug': $(this).attr('data-groupSlug')}),
            type: 'POST',
            data: {
                user_role_requested: $(this).attr('data-role')
            },
            dataType: 'html',
            success: function (data)
            {
                $('div.add-user-modal-content').html(data);
            }
        });
    });

// Listener sur le click des boutons "Créer l'enseignant/élève" et "Créer et continuer"
    $('.btn-create-user, .btn-create-user-more').live('click', function(event)
    {
        event.preventDefault();

        var $canSubmit = true, $errorString = 'Ce champ doit être renseigné', $firstEmptyField = null;
        $('form#add-user-classroom-form').find('input[type=text], input[type=email]').each(function() {
            if ($.trim($(this).val()) == '') {
                if ($firstEmptyField == null) {
                    $firstEmptyField = $(this);
                }
                $canSubmit = false;
                $(this).attr('placeholder', $errorString);
            }
            else if ($(this).attr('type') == 'email') {
                var pattern = new RegExp('^[a-z0-9]+([_|\.|-]{1}[a-z0-9]+)*@[a-z0-9]+([_|\.|-]{1}[a-z0-9]+)*[\.]{1}[a-z]{2,6}$', 'i');
                $canSubmit = pattern.test($(this).val());
                if (!$canSubmit) {
                    $(this).val('');
                    $(this).attr('placeholder', 'E-mail saisi invalide');
                }
            }
        });

        if (!$canSubmit) {
            $firstEmptyField.focus();

            return false;
        }

        $('div.create-user-success').hide();

        $('form#add-user-classroom-form').submit();
        // On vérifie si le click s'est opéré sur le bouton "créer" ou "créer et continuer"
        if ($(this).hasClass('btn-create-user-more')) {
            $createMore = true;
        }else{
            $createMore = false;
        }

        // On cache les boutons et le formulaire
        $('div.create-buttons-container').hide();
        $('div.create-user-form-container').hide();
        // On affiche la div avec le loader
        $('div.create-user-loader').show();
    });



// Listener sur le clic des liens "Ajouter un groupe"
    $('a.add-group-button').click(function (event)
    {
        $('span.group-type-label').html($(this).attr('data-group-type-label'));
        $('div.add-group-modal-content').html('');
        $('div.create-group-success').hide();

        event.preventDefault();
        $.ajax({
            url: Routing.generate('BNSAppGroupBundle_group_add_group_modal_body',{'groupSlug' : $(this).attr('data-groupSlug')}),
            type: 'POST',
            data: {
                group_type_requested: $(this).attr('data-group-type'),
            },
            dataType: 'html',
            success: function (data)
            {
                $('div.add-group-modal-content').html(data);
                $('div.create-group-success').slideUp('fast').delay(5000).slideDown();
            }
        });
    });

    $('.btn-create-group').live('click', function(event){
        $('#group-add-form').submit();
    });

    $('#group_list_select').change(function(){
        var route = Routing.generate('BNSAppGroupBundle_group_list_all', { "type": $(this).val() });
        window.location = route;
    });


    $( "#user-finder" ).autocomplete({
      source: Routing.generate('BNSAppGroupBundle_user_addable_list'),
      minLength: 2,
      delay: 1000,
      select: function( event, ui ) {
        $('#user-quick-linker-submit').show('slow');
        $('#user-finder-value').val(ui.item.id);
          $('body').css('overflow','inherit');
      },
      search: function( event, ui ) {
        $('#user-quick-linker-loader').show();
      },
      open: function( event, ui ) {
        $('#user-quick-linker-loader').hide();
        $('body').css('overflow','hidden');
      },
      close: function( event, ui ) {
        $('body').css('overflow','inherit');
      }
    });



    $('form#user-quick-linker').ajaxForm({
          dataType: 'html',
          success: function(data) {
              $('#user-quick-linker-loader').hide();
              $('#user-quick-linker-return').html(data);
              $('#user-quick-linker-return').show('slow');
              $("#user-finder").val('');
          }
    });
    $('#user-quick-linker-submit').click(function(event){
        event.preventDefault();
        $('#user-quick-linker-loader').show();
        $('#user-quick-linker').submit();
    });

    $( "#group-finder" ).autocomplete({
        source: Routing.generate('BNSAppGroupBundle_group_addable_list'),
        minLength: 2,
        delay: 1000,
        select: function( event, ui ) {
            $('#addToGroup_group_id').val(ui.item.id);
        },
        search: function( event, ui ) {
            /*$('#group-quick-linker-loader').show();*/
        },
        open: function( event, ui ) {
            /*$('#group-quick-linker-loader').hide();*/
        }
    });
});

