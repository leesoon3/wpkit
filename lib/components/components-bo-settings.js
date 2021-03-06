var WpakComponents = (function ($){
		
		var wpak = {};
		
		var get_parent_form_id = function(element){
			return $(element).closest('div.component-form').attr('id');
		};
		
		wpak.ajax_update_component_options = function(element,component_type,action,params){
			var form_id = get_parent_form_id(element);
			var data = {
				action: 'wpak_update_component_options',
				component_type: component_type,
				wpak_action: action,
				params: params,
				post_id: wpak_components.post_id,
				nonce: wpak_components.nonce
			};
			$.post(ajaxurl, data, function(response) {
				$('.ajax-target',$('#'+form_id)).html(response);
			});
		};
		
		wpak.ajax_update_component_type = function(element,component_type){
			var form_id = get_parent_form_id(element);
			var data = {
				action: 'wpak_update_component_type',
				component_type: component_type,
				post_id: wpak_components.post_id,
				nonce: wpak_components.nonce
			};
			$.post(ajaxurl, data, function(response) {
				$('.component-options-target',$('#'+form_id)).html(response);
			});
		};
		
		wpak.ajax_add_or_edit_component_row = function(data,callback){
			var data = {
				action: 'wpak_edit_component',
				wpak_action: 'add_or_update',
				data: data,
				post_id: wpak_components.post_id,
				nonce: wpak_components.nonce
			};
			$.ajax({
			  type: "POST",
			  url: ajaxurl,
			  data: data,
			  success: function(answer) {
				  callback(answer);
			  },
			  error: function(jqXHR, textStatus, errorThrown){
				  callback({'ok':0,'type':'error','message':'Error submitting data'}); //TODO translate js messages
			  },
			  dataType: 'json'
			});
			
		};
		
		wpak.ajax_delete_component_row = function(post_id,component_id,callback){
			
			var data = {
				action: 'wpak_edit_component',
				wpak_action: 'delete',
				data:  {'component_id': component_id, 'post_id': post_id},
				post_id: post_id,
				nonce: wpak_components.nonce
			};
			
			$.ajax({
				  type: "POST",
				  url: ajaxurl,
				  data: data,
				  success: function(answer) {
					  callback(answer);
				  },
				  error: function(jqXHR, textStatus, errorThrown){
					  callback({'ok':0,'type':'error','message':'Error deleting component'}); //TODO translate js messages
				  },
				  dataType: 'json'
			});
		};
		
		return wpak;
		
})(jQuery);

jQuery().ready(function(){
	var $ = jQuery;
	
	function serializeObject(a){
	    var o = {};
	    $.each(a, function() {
	        if (o[this.name] !== undefined) {
	            if (!o[this.name].push) {
	                o[this.name] = [o[this.name]];
	            }
	            o[this.name].push(this.value || '');
	        } else {
	            o[this.name] = this.value || '';
	        }
	    });
	    return o;
	};
	
	function display_feedback(type,message){
		$('#components-feedback').removeClass().addClass(type).html(message).show();
	}; 
	
	function hide_feedback(){
		$('#components-feedback').hide();
	}; 
	
	$('#components-wrapper').on('click','a.component-form-submit',function(e){
		e.preventDefault();
		$('#components-feedback').hide();
		var component_id = $(this).data('id');
		var edit = parseInt(component_id) != 0;
		var form_tr = edit ? $(this).parents('tr').eq(0) : null;
		var data = $('div#component-form-'+ component_id).find("select, textarea, input").serializeArray();
		if( !edit && confirm(wpak_components.messages.confirm_add) || edit && confirm(wpak_components.messages.confirm_edit) ){
			WpakComponents.ajax_add_or_edit_component_row(serializeObject(data),function(answer){
				if( answer.ok == 1 ){
					var table = $('#components-table');
					if( !edit ){
						$('tr.no-component-yet',table).remove();
						table.append(answer.html);
						$('#new-component-form').slideUp();
						$('#new-component-form input.can-reset').attr('value','');
					}else{
						form_tr.prev('tr').replaceWith(answer.html);
						form_tr.remove();
					}
					WpakNavigation.refresh_available_components();
				}
				display_feedback(answer.type,answer.message);
			});
		}
	});
	
	$('#components-wrapper').on('click','a.editinline',function(e){
		e.preventDefault();
		$('#new-component-form').slideUp();
		var id = $(this).data('edit-id');
		$('#edit-component-wrapper-'+id).show();
		$(this).parents('tr').eq(0).hide();
	});
	
	$('#components-wrapper').on('click','tr.edit-component-wrapper a.cancel',function(e){
		e.preventDefault();
		var form_tr = $(this).parents('tr').eq(0);
		form_tr.hide();
		form_tr.prev('tr').show();
	});
	
	$('#components-wrapper').on('click','a.delete_component',function(e){
		e.preventDefault();
		$('#components-feedback').hide();
		$('#new-component-form').slideUp();
		var component_id = $(this).data('id');
		var post_id = $(this).data('post-id');
		//if( confirm(wpak_components.messages.confirm_delete) ){
			WpakComponents.ajax_delete_component_row(post_id,component_id,function(answer){
				if( answer.ok == 1 ){
					$('#components-table tr#component-row-'+component_id).remove();
					$('#components-table tr#edit-component-wrapper-'+component_id).remove();
					WpakNavigation.refresh_available_components();
				}
				display_feedback(answer.type,answer.message);
			});
		//}
	});
	
	$('#add-new-component').click(function(e){
		e.preventDefault();
		hide_feedback();
		$('#new-component-form').slideToggle();
	});
	
	$('#cancel-new-component').click(function(e){
		e.preventDefault();
		hide_feedback();
		$('#new-component-form input.can-reset').attr('value','');
		$('#new-component-form').slideUp();
	});
	
	$('#components-wrapper').on('change','.component-type',function(e){
		var type = $(this).find(":selected").val();
		WpakComponents.ajax_update_component_type(this,type);
	});
	
});