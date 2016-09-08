<?php

function bbppu_ajax_mark_single_forum_as_read(){
    
    $result = array(
            'success'       => false,
            'message'       => null,
            'input_data'    => $_POST
    );

    if ( !isset($_POST['forum_id']) ) {
        
        $result['message'] = 'invalid forum ID';
        
    }else{
        
        $forum_id = $_POST['forum_id'];
        $nonce_action = 'bbppu-mark-as-read_' . $forum_id;
        
        
        if( !wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) ){
            $result['message'] = 'invalid nonce';
        }else{
            if ( bbppu()->mark_single_forum_as_read($forum_id) ){
                $result['success'] = true;
            }
        }
        
    }

    header('Content-type: application/json');
    echo json_encode($result);
    die();
    
    
    
}

add_action('wp_ajax_bbppu_mark_single_forum_as_read', 'bbppu_ajax_mark_single_forum_as_read');
?>