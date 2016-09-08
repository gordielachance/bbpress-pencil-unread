jQuery(document).ready(function($){
    
    /*
    Mark as read when clicking a forum/topic; so if the user goes back with its browser, the forum/topic is not marked as unread.
    //TO FIX not working + maybe there is a better method to avoid this ?

    $('.bbppu-unread .bbp-forum-info a.bbp-forum-title, .bbppu-unread .bbp-topic-title a.bbp-topic-permalink').click(function(event){
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
        
        ajax_data._wpnonce=link.data("nonce");
        ajax_data.forum_id=link.data("forum");
        ajax_data.action='bbppu-mark-as-read_' + ajax_data.forum_id;

        $.ajax({
    
            type: "post",url: ajaxurl,data:ajax_data,
            beforeSend: function() {
                link.addClass('loading');
            },
            success: function(result){
                if(result){
                    block.html(bbppuL10n.marked_as_read);
                    var topics = $('.bbp-body .hentry.topic');
                    topics.removeClass('bbppu-unread').addClass('bbppu-read');
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
