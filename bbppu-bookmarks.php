<?php

class bbP_Pencil_Unread_Bookmarks {
    
    function __construct(){

        add_action('bbp_init',array(&$this,"logged_in_user_actions"));      //logged in users actions
    
    }
    
    function logged_in_user_actions(){
        if(!is_user_logged_in()) return false;
        //TO FIX reset bookmark_id when displaying a topic ?
        add_action( 'bbp_theme_after_reply_content', array(&$this,'bookmark_resfresh'));
        add_action( 'bbp_template_after_replies_loop', array(&$this,'bookmark_save'));
        add_action( 'bbp_theme_after_topic_title', array(&$this,'bookmark_embed_link'));
    }
    
    function bookmark_resfresh(){
        $this->bookmark_id = bbp_get_reply_id(); //last reply viewed
    }
    
    function bookmark_get($parent_id) {
        $user_id = get_current_user_id();
        $metakey_name = 'bookmark_id_'.$parent_id;
        return get_user_meta( $user_id, $metakey_name, true );
    }
    
    function bookmark_save() {
        global $post;
        
        $bookmark_id = $this->bookmark_id;
        
        $parent_id = wp_get_post_parent_id( $bookmark_id ); //topic id
        
        $user_id = get_current_user_id();

        $metakey_name = 'bookmark_id_'.$parent_id;

        $debug_message = sprintf('save bookmark: %s ("%s") for user #%s',$metakey_name,$bookmark_id,$user_id);
        bbppu()->debug_log($debug_message);
        
        return update_user_meta( $user_id, $metakey_name, $bookmark_id );
    }
    
    function bookmark_get_url($post_id){
        
        //$parent_id = wp_get_post_parent_id( $post_id );
        //$url = sprintf('%s#%s',get_permalink($post_id),'post-'.$post_id);
        
        $url = bbp_get_reply_url($post_id);

        return $url;
    }
    
    function bookmark_embed_link($post_id = false){
        
        global $post;
        if (!$post_id) $post_id = $post->ID;
        
        if ( bbppu()->has_user_read($post_id) ) return false;
        
        
        $bookmark_id = $this->bookmark_get($post_id);
        $last_chilren_id = bbp_get_topic_last_reply_id( $post_id );
        
        // Abord if the user has not read any posts in the topic
        if (!$bookmark_id) return;
        
        // Skip if user as read the last post
        if ($bookmark_id == $last_chilren_id) return;
        
        $bookmark_url = $this->bookmark_get_url($bookmark_id);
        $title = __('Go to bookmark','bbppu');
        $icon = '<span class="bbppu-bookmark-icon dashicons dashicons-flag"></span>';
        printf('<a class="bbppu-bookmark" title="%s" href="%s">%s</a>',$title,$bookmark_url,$icon);
    }
    
    
}

new bbP_Pencil_Unread_Bookmarks;