<?php

function bbppu_user_has_read_topic( $topic_id,$user_id=false ){ 
    return bbppu()->has_user_read($topic_id,$user_id);
}

function bbppu_user_has_read_forum( $forum_id,$user_id=false ){ 
    return bbppu()->has_user_read($forum_id,$user_id);
} 

function bbppu_classes($classes){
    echo wp_music_get_classes($classes);
}

function bbppu_get_classes($classes){
    if (empty($classes)) return;
    return' class="'.implode(' ',$classes).'"';
}

?>