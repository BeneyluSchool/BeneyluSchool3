var pop = $("[aria-haspopup='true']");

    pop.live('click', function () {
        $(".mce-floatpanel").css('top', $(this).offset()['top'] + 30);
    });

    $('.mce-widget').live('hover', function(){
       $('.mce-tooltip').css('top',  $(this).offset()['top'] + 30);
    });

