(function($){
	$.fn.tags = function(options){
		var origin_element = this;
		
		options = options || {};
		options.separator = options.separator || ',';
		options.add = options.add || function(){ };
		options.remove = options.remove || function(){ };
		
		var o = {
			e: null,
			ed: null,
			tags:[],
			sep: options.separator,
			addCallBack: options.add,
			removeCallBack: options.remove,
			
			init:function(){
				this.e = origin_element;
				var that = this;
	
				var n = $(this.e).next();
				if ( /jq_tags_editor/.test(n.attr('class')) ){	
					this.ed = n.get(0);
				}			
				else{
					$(this.e).after('<div class="jq_tags_editor"><div class="jq_tags_tokens"></div><input type="text" class="jq_tags_editor_input" /></div>');
					this.ed = $(this.e).next();
				}
	
				$(this.e).hide();
				$(this.ed)
					.unbind()
					.click(function(){
						$(that.ed).find('input').focus();
					})
					.find('input')
						.unbind()
						.blur(function(){
							that.add_tag();
						})
						.keypress(function(e){
							switch(e.which){
								case 13:	// Return is pressed
								case that.sep.charCodeAt(0): // separator is pressed
									e.preventDefault();
									that.add_tag();
									break;
							}
						});
	
				r = $(this.e).val().split( this.sep );
				this.tags = []
				for(i in r){				
					r[i] = r[i].replace( new RegExp('["' + this.sep + ' ]', 'gi'), '');
					if(r[i] != ''){	
						this.tags.push(r[i]);	
					}
				}
				this.refresh_list();			
			},
			
			add_tag:function(){
				var tag_txt = $(this.ed).find('input')
					.val().replace( new RegExp('[' + this.sep + ']', 'gi'), '');
                                        
                                if(isValidEmail(tag_txt))
                                {
                                    if( (tag_txt != '') && (jQuery.inArray(tag_txt, this.tags) < 0) ){
                                            this.tags.push(tag_txt);
                                            this.refresh_list();
                                    }
                                }

				$(this.ed).find('input').val('');
				this.addCallBack(tag_txt, this);
			},		
			remove_tag:function(tag_txt){
				r = [];
				for(i in this.tags){
					if(this.tags[i] != tag_txt){	
						r.push(this.tags[i]);	
					}
				}
				this.tags = r;
				this.refresh_list();
				this.removeCallBack(tag_txt, this);
			},		
			refresh_list: function(){
				var that = this;
				
				$(this.ed).find('div.jq_tags_tokens').html('');
				$(this.e).val(this.tags.join(this.sep + ' '));
				
				h = '';
				for(i in that.tags){
					h += '<div class="jq_tags_token"><a href="#">x</a>' + that.tags[i] + '</div>';
				}
				$(that.ed).find('input').val('');
				$(that.ed)
					.find('div.jq_tags_tokens')
						.html(h)
						.find('div.jq_tags_token')
							.children('a')
								.click(function(){
									var tag_txt = $(this).parents('.jq_tags_token:first').html().replace(/<a(.*?)<\/a>/i, '').replace(/<\/(.*?)>/i, ''); 
									that.remove_tag(tag_txt);
									return false;
								});
			}
			
		};
		o.init();
		
		return this;
		
	};
})(jQuery);


function isValidEmail(str) {
    var atSym = str.lastIndexOf("@");
    if (atSym < 1) { return false; } 
    if (atSym == str.length - 1) { return false; } 
    if (atSym > 64) { return false; } 
    if (str.length - atSym > 255) { return false; } 

    var lastDot = str.lastIndexOf(".");
    if (lastDot > atSym + 1 && lastDot < str.length - 1) { return true; }
    if (str.charAt(atSym + 1) == '[' &&  str.charAt(str.length - 1) == ']') { return true; }
    return false;
}
