/*
	
	Ajax - JQuery for Admin Area
	
*/
(function ($) {
    $(document).ready(function () {
        colorMenuIcons();
    });

    function colorMenuIcons() {
        changeMenuId("menu-posts-videos");
        changeMenuId("menu-posts-faqs");
        changeMenuId("menu-posts-editors");
        changeMenuId("toplevel_page_optipub-resources");
        changeMenuId("toplevel_page_optipub");
        changeMenuId("menu-posts-pubs-detailed");
    }

    function changeMenuId(id) {
        var articlesCPT = $('#' + id);
        if (articlesCPT.length > 0) {
            articlesCPT.find('.wp-menu-image').addClass('optipub-icon-colors');
            articlesCPT.find('.wp-menu-name').addClass('optipub-icon-colors');
        }
    }
})
(jQuery);
