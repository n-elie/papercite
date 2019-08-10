

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

    // when the content is desselected when clicking on the sidebar from post editor it will the
    // blur event
    $j(document).on('blur','.is-selected',function(evt)   {
        console.log('Unfocus',this);
        $j('#was-selected').removeAttr('id');
        $j(this).attr('id','was-selected');
    });

    $j('.papercite-metabox-bibentry button').click(function (evt) {
        var but = evt.target;
        var text = $j(but).text();
        console.log(but,text);
        // add into text editor
        //$('.is-selected').append('abc')
        $j('#was-selected').append(text);
    });

    $j(document).on('click','#papercite-entries-search',function(evt) {
            $j(this).val('');
    }).on('keyup','#papercite-entries-search',function(evt) {
        var text = $j(this).val();
        console.log(text);
        
            $j('#papercite-metabox-content').find('li').hide();
            $j('#papercite-metabox-content').find('li').has(':contains(' + text + ')').show();
/*            $j('#papercite-metabox-content').find('li').each(function (i, elem) {

                if ($j(elem).has(':contains(' + text + ')')) {
                    $j(elem).show();
                    console.log('show',i,elem);
                } else {
                    $j(elem).hide();
                }
            });
*/            //PpcsubmitSearch(text);
        
    });

    function PpcsubmitSearch(q) {
        $j.get(ajaxurl ,
            { action : 'search_citations',q : q } )
            .success(
            function (response) {
                console.log(response);
                response.forEach(function(item) {
                });
            })
            .error(function (error) {
            console.error(error);
        });
    }

});
