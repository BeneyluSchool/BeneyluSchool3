$(document).ready(function() 
{
    if($.browser.msie && parseInt($.browser.version) < 8)
    {
        $('#logon-iframe').remove();
        $('.browser-error').show();
    }
});