$( ".toggle-add-teacher" ).click(function() {
	$( ".add-teacher-form" ).fadeToggle("show", "linear");
});

$( ".toggle-add-pupil" ).click(function() {
	$( ".add-pupil-form" ).fadeToggle("show", "linear");
});

$( ".toggle-add-classroom" ).click(function() {
	$( ".add-classroom-form" ).fadeToggle("show", "linear");
});

$( ".delete-teacher-img, .delete-pupil-img" ).click(function() {
	var $this = $(this);
	var elements = $this.parent().attr('id');
	elements = elements.split('_');
	var schoolSlug = elements[0];
	var userId = elements[2];
	var route;
	if(elements[1] == 'teacher')
	{
		route = Routing.generate('BNSAppSchoolBundle_users_delete_teacher', { 'slug': schoolSlug, 'id': userId });
	}
	else
	{
		route = Routing.generate('BNSAppSchoolBundle_users_delete_pupil', { 'slug': schoolSlug, 'id': userId });
	}
	$.get(route, 
		function(data) {
			if (data == 'false') 
			{
				
			}
			if (data == 'true') 
			{
				$this.parent().parent().fadeToggle("show", "linear");
			}
		}
	);
});


$( ".active, .inactive" ).click(function() {
	var $this = $(this);
	var elements = $this.attr('id').split('_');
	var schoolSlug = elements[0];
	var userId = elements[2];
	var route;
	if (elements[1] == "director")
	{
		route = Routing.generate('BNSAppSchoolBundle_users_switch_director', { 'slug': schoolSlug, 'id': userId });
	}
	else
	{
		route = Routing.generate('BNSAppSchoolBundle_users_switch_teacher', { 'slug': schoolSlug, 'id': userId });
	}
	$.get(route, 
		function(data) {
			if (data == 'false') 
			{
				
			}
			if (data == 'true') 
			{
				$this.toggleClass('active inactive');
			}
		}
	);
});

$(".switch-permission").click(function() {
	var $this = $(this);
	var loadingImg = "<img src=\"/medias/images/icons/text_loading.gif\" />";
	if ($this.html() == loadingImg)
	{
		return false;
	}
	$this.html(loadingImg);

	/*
	 *  TODO: il faudra modifier et récupérer l'attribut id et non class; actuellement on fait avec class car
	 *		  plusieurs permissions portes le même id (étant donné que l'on récupère pas des id uniques depuis la centrale)
	 */
	var elements = $(this).parent().attr('class').split('_');
	var role = elements[2];
	var permission = elements[4];
	var schoolSlug = elements[0];
	$.get(Routing.generate('BNSAppSchoolBundle_right_manager_switch_permission', { 'slug': schoolSlug, 'role' : role, 'permission' : permission }),
		function(data)
		{
			if (data == 'true')
			{
				$this.toggleClass('allow forbid').html('Autorisée');
			}
			if (data == 'false')
			{
				$this.toggleClass('allow forbid').html('Interdit');
			}
		}
	);
});

$(".switch-module").click(function() {
	var $this = $(this);
	var loadingImg = "/medias/images/icons/loading.gif";
	if ($this.attr("src") == loadingImg)
	{
		return false;
	}
	$this.attr("src", loadingImg);
	/*
	 *  TODO: il faudra modifier et récupérer l'attribut id et non class; actuellement on fait avec class car
	 *		  plusieurs permissions portes le même id (étant donné que l'on récupère pas des id uniques depuis la centrale)
	 */
	var elements = $this.attr('id').split('_');
	var role = elements[3];
	var module = elements[1];
	var schoolSlug = elements[0];
	$.get(Routing.generate('BNSAppSchoolBundle_right_manager_switch_module', { 'slug': schoolSlug, 'role' : role, 'module' : module }),
		function(data)
		{
			if (data == 'true')
			{
				$this.attr("src", "/medias/images/icons/fugue/tick.png");
			}
			if (data == 'false')
			{
				$this.attr("src", "/medias/images/icons/fugue/cross.png");
			}
		}
	);
});