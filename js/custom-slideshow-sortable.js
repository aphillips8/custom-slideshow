 jQuery(document).ready(function($) {

	$(".images-sortable").sortable({axis: "y", handle: ".drag", containment: "parent", cursor: "move", items: "tr", opacity: 0.6, tolerance: 'pointer', helper: function(e, tr) {
		var originals = tr.children();
		var helper = tr.clone();
		helper.children().each(function(index) {
			// Set helper cell sizes to match the original sizes
			$(this).width(originals.eq(index).width());
		});
		return helper;
	} });
	
	// AJAX to save sortable results
	var custom_slideshow_sortable_images_order = new Array();
	
	$(".images-sortable").bind("sortupdate", function(event, ui) {
		custom_slideshow_sortable_images_order = [];
		
		$(this).find("tr input.custom-slideshow-image-id").each(function(index) {
			custom_slideshow_sortable_images_order.push($(this).attr("value"));
		});
		
		var data = {
			action: 'custom_slideshow_sortable_images_save',
			custom_slideshow_sortable_images_order_data: custom_slideshow_sortable_images_order
		};
		
		jQuery.post(ajaxurl, data, function(response) {
			//Javascript response here
			//alert(response);
		});
	});

 });