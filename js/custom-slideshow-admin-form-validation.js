function add_new_custom_slideshow_image_validate() {
	var slideshow_image = document.getElementById("slideshow_image").value,
	file_description = document.getElementById("file_description").value,
	dest_page = document.getElementById("dest_page").value;
	
	if(!slideshow_image) {
		alert("Please choose an image.");
		return false;
	}
	
	if(!file_description) {
		alert("Please enter a description.");
		return false;
	}
	
	if(dest_page == "") {
		alert("Please select a page for the image.");
		return false;
	}
	
	return true;
}