<?php

class bbP_Pencil_Unread_Forum_Marks {
    
    static $marked_forums_metaname = 'bbppu_marked_forums'; // contains an array of 'marked as read' timestamps for forums (stored in usermeta)
    
    function __construct(){
        add_action('bbp_init',array(&$this,"logged_in_user_actions"));      //logged in users actions
    
    }
    
    function logged_in_user_actions(){
        if(!is_user_logged_in()) return false;
        
            add_action( 'bbp_template_before_forums_index', array(&$this,'mark_as_read_link'));
            add_action( 'bbp_template_before_single_forum', array(&$this,'mark_as_read_link'));
            add_action("wp", array(&$this,"process_mark_as_read"));    //process "mark as read" link
        
            add_action('wp_ajax_bbppu_mark_single_forum_as_read', array(&$this,'ajax_mark_single_forum_as_read')); //ajax
        
    }
    
    function ajax_mark_single_forum_as_read(){

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
                if ( bbP_Pencil_Unread_Forum_Marks::mark_single_forum_as_read($forum_id) ){
                    $result['success'] = true;
                }
            }

        }

        header('Content-type: application/json');
        echo json_encode($result);
        die();



    }
    
    function mark_as_read_link( $args = array() ){
        echo $this->get_mark_as_read_link($args);
    }
    

    function get_mark_as_read_link( $args = array() ){
		// No link
		$retval = false;

		// Parse the arguments
		$r = bbp_parse_args( $args, array(
			'forum_id'    => 0,
			'user_id'     => 0,
			'before'      => '',
			'after'       => ''
		), 'bbppu_get_mark_as_read_link' );

		// No link for categories until we support subscription hierarchy
		// @see http://bbpress.trac.wordpress.org/ticket/2475
		//if ( ! bbp_is_forum_category() ) {
			$retval = $this->get_user_mark_as_read_link( $r );
		//}
        
        return $retval;
    }
    
	function get_user_mark_as_read_link( $args = '', $user_id = 0, $wrap = true ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'user_id'     => 0,
			'topic_id'    => 0,
			'forum_id'    => 0,
			'before'      => '&nbsp;|&nbsp;',
			'after'       => ''
		), 'bbppu_get_user_mark_as_read_link' );

		// Validate user and object ID's
		$user_id  = bbp_get_user_id( $r['user_id'], true, true );
		$topic_id = bbp_get_topic_id( $r['topic_id'] );
		$forum_id = bbp_get_forum_id( $r['forum_id'] );
		if ( empty( $user_id ) || ( empty( $topic_id ) && empty( $forum_id ) ) ) {
			return false;
		}

		// No link if you can't edit yourself
        /*
		if ( ! current_user_can( 'edit_user', (int) $user_id ) ) {
			return false;
		}
        */

		// Check if viewing a single forum
		if ( empty( $topic_id ) && ! empty( $forum_id ) ) {
            
            $new_activity = true;
            
            
            //this forum or its ancestor have been marked has read
            if ( $forum_time_marked = self::get_forum_marked_time($forum_id,$user_id) ){
                $last_active_time = bbp_convert_date(get_post_meta( $forum_id, '_bbp_last_active_time', true )); //get post last activity time
                $new_activity = ($last_active_time > $forum_time_marked);
            }
            
            if (!$new_activity){ //no new activity since marked
                //$link_html = __('Marked as read','bbppu') . sprintf('<small> (%s, %s)</small>',date_i18n(get_option( 'date_format' ),$forum_time_marked),date_i18n(get_option( 'time_format' ),$forum_time_marked));
                $link_html = __('Marked as read','bbppu');
            }else{
                $text       = __('Mark all as read','bbppu');
                $loading_icon = '<i class="bbppu-loading fa fa-circle-o-notch fa-spin fa-fw"></i>';
            
                $query_args = array( 
                    'action' => 'bbpu_mark_read',
                    'forum_id' => $forum_id 
                );

                // Create the link based where the user is and if the user is
                // subscribed already
                if ( bbp_is_subscriptions() ) {
                    $permalink = bbp_get_subscriptions_permalink( $user_id );
                } elseif ( bbp_is_single_forum() || bbp_is_single_reply() ) {
                    $permalink = bbp_get_forum_permalink( $forum_id );
                } else {
                    $permalink = get_permalink();
                }

                $nonce_action = 'bbppu-mark-as-read_' . $forum_id;
                $nonce = wp_create_nonce($nonce_action);
                $url = add_query_arg( $query_args, $permalink );
                $url = esc_url( wp_nonce_url( $url,$nonce_action) );

                $link_html = sprintf('<a href="%s" data-forum="%d" data-nonce="%s">%s</a>',$url, $forum_id, $nonce, $loading_icon.$text);
            }
            
            $classes  = array('bbppu-mark-as-read');
            $classes_str = bbppu_get_classes($classes);

			$html = sprintf( '%s<span id="bbppu-mark-as-read-%d"  %s>%s</span>%s', $r['before'], $forum_id, $classes_str, $link_html, $r['after'] );

			// Initial output is wrapped in a span, ajax output is hooked to this
			if ( !empty( $wrap ) ) {
				$html = '<span id="bbppu-mark-as-read">' . $html . '</span>';
			}

		} else {

			// Decide which link to show
			$is_subscribed = bbp_is_user_subscribed_to_topic( $user_id, $topic_id );
			if ( ! empty( $is_subscribed ) ) {
				$text       = $r['unsubscribe'];
				$query_args = array( 'action' => 'bbp_unsubscribe', 'topic_id' => $topic_id );
			} else {
				$text       = $r['subscribe'];
				$query_args = array( 'action' => 'bbp_subscribe',   'topic_id' => $topic_id );
			}

			// Create the link based where the user is and if the user is
			// subscribed already
			if ( bbp_is_subscriptions() ) {
				$permalink = bbp_get_subscriptions_permalink( $user_id );
			} elseif ( bbp_is_single_topic() || bbp_is_single_reply() ) {
				$permalink = bbp_get_topic_permalink( $topic_id );
			} else {
				$permalink = get_permalink();
			}

			$url  = esc_url( wp_nonce_url( add_query_arg( $query_args, $permalink ), 'toggle-subscription_' . $topic_id ) );
			$sub  = $is_subscribed ? ' class="is-subscribed"' : '';
			$html = sprintf( '%s<span id="subscribe-%d"  %s><a href="%s" class="subscription-toggle" data-topic="%d">%s</a></span>%s', $r['before'], $topic_id, $sub, $url, $topic_id, $text, $r['after'] );

			// Initial output is wrapped in a span, ajax output is hooked to this
			if ( !empty( $wrap ) ) {
				$html = '<span id="subscription-toggle">' . $html . '</span>';
			}
		}

		// Return the link
		return apply_filters( 'bbppu_get_user_mark_as_read_link', $html, $r, $user_id, $topic_id );
	}
    
    function process_mark_as_read() {
        
        if( !isset( $_REQUEST['action'] ) || $_REQUEST['action'] != 'bbpu_mark_read' )return false;

        // Bail if no forum ID is passed
        if ( empty( $_REQUEST['forum_id'] ) ) {
            return;
        }

        // Get required data
        $user_id  = bbp_get_user_id( 0, true, true );
        $forum_id = intval( $_REQUEST['forum_id'] );

        // Check for empty forum
        if ( empty( $forum_id ) ) {
            bbp_add_error( 'bbppu_forum_id', __( '<strong>ERROR</strong>: No forum was found! Which forum are you marking as read ?', 'bbppu' ) );
            
        } else{
            
            $nonce_action = 'bbppu-mark-as-read_' . $forum_id;
            
            if( !wp_verify_nonce( $_REQUEST['_wpnonce'], $nonce_action ) ){
                bbp_add_error( 'bbppu_forum_id', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
            }
        }

        // Bail if we have errors
        if ( bbp_has_errors() ) {
            return;
        }

        /** No errors *************************************************************/

        $success = self::mark_single_forum_as_read($forum_id);

        // Success!
        if ( !$success ) {
             bbp_add_error( 'bbppu_mark_forum_as_read', __( '<strong>ERROR</strong>: There was a problem marking this forum as read!', 'bbppu' ) );
        }
    }

    static function get_marked_forums($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        return get_user_meta($user_id, self::$marked_forums_metaname,true);
    }

    /*
     * Marks forum as "read".
     * Remove marks for descendants if any.
     */
        
	static function mark_single_forum_as_read($forum_id) {

            //validate user
            $user_id = get_current_user_id();
            if(!$user_id) return false;
            
            $marked_forums = self::get_marked_forums();

            $marked_forums[$forum_id] = current_time('timestamp');

            //remove old marks for descendants if any.
            if ( $subforums = bbp_forum_get_subforums($forum_id)){
                foreach ($subforums as $subforum) {
                    unset($marked_forums[$subforum->ID]);
                }
            }

            return update_user_meta($user_id, self::$marked_forums_metaname, $marked_forums );
	}
    
    /*
     * Check if the forum (or forum ancestor) has been set as read
     */

    static function get_forum_marked_time($post_id = null, $user_id = null, $include_ancestors = true){

        $time = null;

        //validate user
        if(!$user_id) $user_id = get_current_user_id();
        $userdata = get_userdata( $user_id );

        if ( $userdata && ( $usermarks = self::get_marked_forums($user_id) ) ) {

            $ancestormarks = array();

            //get forum ID
            $forum_id  = bbppu()->get_forum_id_for_post( $post_id );

            $ids_to_check = array($forum_id);

            if ($include_ancestors){  //check whole ancestors tree
                $ids_to_check = array_merge( bbp_get_forum_ancestors( $forum_id ), $ids_to_check);
            }

            foreach ( $usermarks as $marked_id => $marked_time ){
                if ( in_array($marked_id, $ids_to_check) ) $ancestormarks[$marked_id] = $marked_time;
            }

            if ( !empty($ancestormarks) ) {
                $time = max($ancestormarks); //get last mark
            }

        }

        return $time;

    }
    
    
}

new bbP_Pencil_Unread_Forum_Marks;
