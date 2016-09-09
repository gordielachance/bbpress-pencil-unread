jQuery(document).ready(function($){
    
    /*
    Mark as read when clicking a forum/topic; so if the user goes back with its browser, the forum/topic is not marked as unread.
    //TO FIX not working + maybe there is a better method to avoid this ?
    
    var forums = $('.bbppu-unread .bbp-forum-info a.bbp-forum-title');
    var topics = $('.bbppu-unread .bbp-topic-title a.bbp-topic-permalink');
    var items = $.merge(forums,topics);

    items.click(function(event){
        var item = $(this).closest(".bbppu-unread");
        item.removeClass("bbppu-unread").addClass("bbppu-read");
    });
    */
        

    $('.bbppu-mark-as-read a').click(function(event){
        
        event.preventDefault();
        
        var link = $(this);
        var block = link.parent('.bbppu-mark-as-read');
        var ajax_data = {};

        if(link.hasClass('loading')) return false;
        
        ajax_data.action='bbppu_mark_single_forum_as_read';
        ajax_data._wpnonce=link.data("nonce");
        ajax_data.forum_id=link.data("forum");

        $.ajax({
    
            type:       "post",
            url:        bbppuL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
            beforeSend: function() {
                link.addClass('loading');
            },
            success: function(data){

                if (data.success === false) {
                    link.addClass('bbppu-error');
                    console.log(data);
                }else if (data.success === true) {
                    block.html(bbppuL10n.marked_as_read);
                    var forums = $('#forums-list-'+ajax_data.forum_id+' .bbppu-hentry');
                    
                    /*
                    //this would also mark super sticky topics, which may be from other forums.
                    var topics = $('#bbp-forum-'+ajax_data.forum_id+' .bbppu-hentry'); 
                    */

                    var topics = $('.bbppu-hentry.bbp-parent-forum-'+ajax_data.forum_id); 
                    
                    var items = $.merge(forums,topics);
                    
                    items.removeClass('bbppu-unread').addClass('bbppu-read');
                }

            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                link.removeClass('loading');
            }
        });
        
        
        
        return false;

    });

});
