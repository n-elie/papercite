(function ($) {

    $('form h2').eq(1).hide().nextUntil('h2,input').hide();
    $('form h2').eq(2).hide().nextUntil('h2,input').hide();
    //$('form h2').nextUntil('h2,input').not('.nav-tab-active')

    $(document).on('click', '.nav-tab-wrapper a', function () {

        $('.nav-tab-wrapper a').removeClass('nav-tab-active');

        $(this).addClass('nav-tab-active');
        $('form h2').each(function (i, h2) {
            $(this).hide();
            $(h2).nextUntil('h2,input').hide();
        });

//		$sectionsToHide = $('section.papercite-section').hide();
//    console.log('hide',$sectionsToHide);//.prev('h2').hide();
        $('form h2').eq($(this).index()).show();
        $('form h2').eq($(this).index()).nextUntil('h2,input').show();
//    console.log('show',$sectionsToShow);//.prev('h2').show();
        return false;
    });

})(jQuery);
