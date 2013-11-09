<?php

function bbppu_ajax_mark_single_forum_as_read(){
    
    if (!isset($_POST['forum_id'])) return false;
    
    $forum_id = $_POST['forum_id'];
    
    if( ! wp_verify_nonce( $_POST['_wpnonce'], 'bbpu_mark_read_single_forum_'.$forum_id ) ) return false;
    
    echo (bool)bbp_pencil_unread()->mark_single_forum_as_read($forum_id);
    die();
}

add_action('wp_ajax_bbppu_mark_single_forum_as_read', 'bbppu_ajax_mark_single_forum_as_read');
?>
