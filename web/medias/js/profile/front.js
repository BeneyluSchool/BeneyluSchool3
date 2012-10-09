$(document).ready(function ()
{
	$('.comment-texterea textarea').keypress(function (e)
	{
		$this = $(this);
		if ($this.val().length > 0 && !event.shiftKey && event.which == 13) // enter
		{
			commentAction(this, e);
		}
	});

	$('.comment-texterea button').click(function (e)
	{
        $this = $(this).parent().find('textarea');
        if ($this.val().length > 0)
        {
            commentAction($this, e);
        }			
	});
});

function commentAction(element, event)
{
	$this = $(element);
    $this.attr('disabled', 'disabled');
    $button = $this.parent().find('button');
    $button.attr('disabled', 'disabled');
	var feedId = $this.attr('id').split('-');
    feedId = feedId[2];
    
	event.preventDefault();
	$.post(
        Routing.generate('BNSAppProfileBundle_comment_add'), 
        {
            feed_id: feedId, 
            text: $this.val()
        }, 
        function success(data)
        {
            $('.feed-'+ feedId +'-comment-container').append(data);
            $this.removeAttr('disabled');
            $button.removeAttr('disabled');
            $this.val('');
        }
    );
}