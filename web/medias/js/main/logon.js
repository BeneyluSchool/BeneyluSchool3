$(document).ready(function() 
{
    if($.browser.msie && parseInt($.browser.version) < 8)
    {
        $('#logon-iframe').remove();
        $('.browser-error').show();
    }

    $('.news-button').click(function(e){
        e.preventDefault();
        $('.news > .news-button').toggleClass('active');
        $('.news > .news-content').toggle('slow');

    });
});