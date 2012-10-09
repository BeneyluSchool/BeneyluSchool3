/*$("span.btn-change-module-state").click(function() {
   $this = $(this);
   var infos = $this.attr('id');
   infos = infos.split('-');
   var moduleUniqueName = infos[1];
   var roleUniqueName = infos[3];
   var currentState = ($this.hasClass('active')? 1 : 0);
   $this.addClass('loading');
   $.get(
        Routing.generate('BNSAppMainBundle_module_activation', { 'contextGroupType': 'TEAM', 'moduleUniqueName': moduleUniqueName, 'roleUniqueName': roleUniqueName, 'currentState': currentState }),
        function(data)
        {
            if (data == "true")
            {
                $this.toggleClass('active inactive');               
            }
            
            $this.removeClass('loading');
            
        }
   );
});*/

// Activation/désactivation d'un module
$("span.btn-change-module-state").live('click',function() {
   var moduleUniqueName = $(this).attr('data-module-unique-name')
   var roleId = $(this).attr('data-role-id');
   var groupId = $(this).attr('data-group-id');
   var currentState = ($(this).hasClass('active')? 1 : 0);
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
   });
});
