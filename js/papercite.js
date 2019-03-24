

var $j = jQuery.noConflict();

$j(document).ready(function() {
    // Toggle Single Bibtex entry
    $j('a.papercite_toggle').click(function() {
	$j( "#" + $j(this).attr("id") + "_block" ).toggle();
	return false;
    });
});

// functionality in metabox
$j(document).ready(function () {
    $j('.papercite-metabox-bibentry button').click(function (evt) {
        $but = $j(evt.target).children();
        //console.log($but.get());
    });
});