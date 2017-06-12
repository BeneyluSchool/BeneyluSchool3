$(function ()
{
    //Fichier reprenant toutes les actions back du module School

    $('.item-list-container .item:not(.disabled)').live("click", function (e)
    {
        window.location = $(e.currentTarget).find('.btn-visualisation').attr('href');
    });

    $('#signal-classroom-submit').click(function(e){
        e.preventDefault();
        $('#signal-classroom-form').submit();
    });

    $('.submit-custom-school-form').click(function(){
        $('#custom-school-form').submit();
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
    $('.submit-blackboard-school-form').click(function() {
        $('form#submit-blackboard-school-form').submit();
    });


// Dropdown buttons
    $('.add-classroom-submit').click(function (e)
    {

        e.preventDefault();

        var $this = $(e.currentTarget),
            $loader = $('#add-classroom-modal .loader');

        var button = $(this);

        $loader.fadeIn('fast');

        $.ajax({
            url: Routing.generate('BNSAppSchoolBundle_back_add_classroom'),
            data: {
                label : $('#add-classroom-label').val()
            },
            complete: function(){
                $loader.fadeOut('fast', function () {
                    $(this).hide();
                });
            },
            success: function (data) {
                $('#add-classroom-form-error').hide();
                if( !button.hasClass('continue'))
                {
                    $('#add-classroom-modal').modal('hide');
                    $('#add-classroom-success').show();
                }else{
                    $('#add-classroom-form-success').show();
                }
                $('#add-classroom-label').val('');
                /* Ajout de la nouvelle classe aux classe déjà présentes */
                $('.item-list-container').prepend(data);
            },
            error: function(data) {
                $('#add-classroom-form-success').hide();
                $('#add-classroom-form-error').show();
            }
        });
    });

    // Close alert for verify result username
    $('body').on('click', '#verify-result', function (e)
    {
        var $this = $(e.currentTarget);
        $row = $this.parent().parent(),
            $username = $('#username-to-check');

        $row.slideUp('fast', function () { $(this).remove() });
        $username.val('');
        $username.focus();
    });

    // Invite teacher to join classroom
    $('body').on('click', '#add-user-modal .verify-result .btn-invite-teacher', function (e)
    {
        var $this = $(e.currentTarget),
            $row = $this.parent().parent().parent()
        $loader = $row.find('.loader'),
            $verifyDiv = $row.find('.verify-result'),
            $usernameInput = $row.find('#username-to-check');

        $loader.fadeIn('fast');

        $.ajax({
            url: $this.attr('href'),
            type: 'POST',
            data: {'username': $this.data('username')},
            success: function ()
            {
                $verifyDiv.slideUp('fast', function () { $(this).remove() });
                $usernameInput.val('');
                $usernameInput.focus();
                $loader.fadeOut('fast');
            }
        });

        return false;
    });

    // invite teacher in school
    $('body').on('click', '.invite-teacher-submit', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $loader = $('#add-classroom-modal').find('.loader');

        $loader.fadeIn('fast');
        $.ajax({
            url: Routing.generate('BNSAppSchoolBundle_back_invite_teacher'),
            data: {
                username: $('#invite-teacher-username').val()
            },
            success: function (data) {
                $('#invite-teacher-form-error').hide();
                if ($button.hasClass('continue')) {
                    $('#invite-teacher-form-success').show();
                } else {
                    $('#invite-teacher-modal').modal('hide');
                    $('#invite-teacher-success').show();
                }
                $('#add-classroom-label').val('');
                /* Ajout de la nouvelle classe aux classe déjà présentes */
                $('.item-list-container').append(data);
            },
            error: function (data) {
                console.error(data);
                $('#invite-teacher-form-success').hide();
                $('#invite-teacher-form-error').show().find('.alert-text').text(data.responseText);
            },
            complete: function(){
                $loader.fadeOut('fast', function () {
                    $(this).hide();
                });
            },
        });
    });

});
