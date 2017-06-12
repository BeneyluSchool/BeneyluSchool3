$(function(){
    //Page de la journée
    $('.btn-change-module-state.absence').click(function(e){
       var $this = $(e.currentTarget);
       if ($this.hasClass('loading')) {
           return false;
       }
       $this.addClass('loading');
       $.ajax({
            url: Routing.generate(
                'BNSAppPupilMonitoringBundle_absence_back_toggle_absence',
                {'login' : $(this).attr('data-login'), 'date' : $(this).attr('data-date'), 'type' : $(this).attr('data-type')}
            ),
            success: function(data){
                $this.toggleClass('active');
                $this.toggleClass('inactive');
            }
        }).done(function ()
        {
            $this.removeClass('loading');
            var $parent = $this.parent('.buttons-container');
            var checkboxStatus = true;
            $parent.children('.btn-change-module-state').each(function(){
                if($(this).hasClass('inactive'))
                {
                    checkboxStatus = false;
                }
            });
            $parent.children('input').prop('disabled',checkboxStatus);
            if(checkboxStatus == true)
            {
                $parent.children('input').prop('checked',false);
            }
        }); 
    });
    $('.legitimate').click(function(e){
       $.ajax({
           url: Routing.generate(
               'BNSAppPupilMonitoringBundle_absence_back_toggle_legitimate',
               {'login' : $(this).attr('data-login'), 'date' : $(this).attr('data-date'), 'legitimate' : $(this).prop('checked') ? 'true' : 'false' }
           )
       });
    });
    //Page de la semaine
    $('.legitimate-week').click(function(e){
       $.ajax({
           url: Routing.generate(
               'BNSAppPupilMonitoringBundle_absence_back_toggle_absence',
               {'login' : $(this).attr('data-login'), 'date' : $(this).attr('data-date'), 'type' : $(this).attr('data-type') }
           )
       });
    });
    
    //LPC
    $('.container-classroom.lpc > .item-list-container > .item').click(function(){
        self.location = $(this).find('a').attr('href');
    });
    
    $('.section').click(function(e){
        //On écarte le clic dans le input
        if(!$(e.srcElement).hasClass('jq-date')){
            $(this).next('ol').first().toggle('slow');
            $(this).find('.toggle-arrow').toggleClass('opened');
        }
    });
    jQuery(function($){$.datepicker.setDefaults($.datepicker.regional['fr']);});
    $(".jq-date").datepicker({});
    $(".jq-date").change(function(){
        var $this = $(this);

        $this.toggleClass('loading');

        if($this.val() == ""){
            var date = 'null';
        }else{
            var date = $this.val().split('/');
            date = date[2] + '-' + date[1] + '-' + date[0];
        }

        $.ajax({
            url: Routing.generate(
                'BNSAppPupilMonitoringBundle_lpc_back_select',
                {'login' : $this.attr('data-login'), 'date' : date, 'lpcSlug' : $(this).attr('id') }
            )
        }).done(function ()
        {
           $this.toggleClass('loading');
        });
    });
});