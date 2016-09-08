<?php
/*
Plugin Name: bbPress Pencil Unread
Plugin URI: http://wordpress.org/extend/plugins/bbpress-pencil-unread
Description: Display which bbPress forums/topics have already been read by the user.
Version: 1.2
Author: G.Breant
Author URI: http://sandbox.pencil2d.org/
Text Domain: bbppu
Domain Path: /languages
*/

class bbP_Pencil_Unread {
	/** Version ***************************************************************/
	
        /**
	 * @public string plugin version
	 */
	public $version = '1.2';
        
	/**
	 * @public string plugin DB version
	 */
	public $db_version = '106';
	
	/** Paths *****************************************************************/
	
        public $file = '';
	
	/**
	 * @public string Basename of the plugin directory
	 */
	public $basename = '';
	/**
	 * @public string Absolute path to the plugin directory
	 */
	public $plugin_dir = '';
        
	/**
	 * @public name of the var used for plugin's actions
	 */
	public $action_varname = 'bbppu_action';
        
	/**
	 * @public meta name for topics having been read
	 */
	public $topic_read_metaname = 'bbppu_read_by';
    
    public $meta_name_options = 'bbppu_options';
        
	/**
         * When creating a new post (topic/reply), set this var to true or false
         * So we can update the forum status after the post creation (keep it read if it was read)
         * @var type 
         */
	public $forum_was_read_before_new_post = false;
        
	/**
	 * @var The one true Instance
	 */
	private static $instance;
        
	/**
	 * Main bbPress Pencil Unread Instance Instance
	 *
	 * @see bbpress_pencil_unread()
	 * @return The one true bbPress Pencil Unread Instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new bbP_Pencil_Unread;
			self::$instance->setup_globals();
			self::$instance->includes();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}
        
	/**
	 * A dummy constructor to prevent from being loaded more than once.
	 *
	 */
	private function __construct() { /* Do nothing here */ }
        
	function setup_globals() {
		/** Paths *************************************************************/
		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url ( $this->file );
        
        $this->options_default = array(
            'test_registration_time'                => 'on' //all items dated befoe user's first visit
        );
        
        $this->options = wp_parse_args(get_option( $this->meta_name_options), $this->options_default);
        
        
	}
        
	function includes(){
            require( $this->plugin_dir . 'bbppu-functions.php');
            require( $this->plugin_dir . 'bbppu-settings.php');
            require( $this->plugin_dir . 'bbppu-template.php');
            require( $this->plugin_dir . 'bbppu-ajax.php');
            if (is_admin()){
            }
	}
	
	function setup_actions(){
            
            /*actions are hooked on bbp hooks so plugin will not crash if bbpress is not enabled*/

            add_action('bbp_init', array($this, 'load_plugin_textdomain'));     //localization
            add_action('bbp_loaded', array($this, 'upgrade'));                  //upgrade
            
            add_action('bbp_init',array(&$this,"logged_in_user_actions"));      //logged in users actions

            //scripts & styles
            add_action('bbp_init', array($this, 'register_scripts_styles'));
            add_action('bbp_enqueue_scripts', array($this, 'scripts_styles'));
            add_action('admin_enqueue_scripts', array($this, 'scripts_styles_admin'));
            
	}
        
	public function load_plugin_textdomain(){
		load_plugin_textdomain('bbppu', FALSE, $this->plugin_dir.'languages/');
	}
        
	function upgrade(){
		global $wpdb;
		
		$version_db_key = 'bbppu-db-version';
		
		$current_version = get_option($version_db_key);

		if ($current_version==$this->db_version) return false;
			
		
		if(!$current_version){  //install
			//require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			//dbDelta($sql);
		}else{  //upgrade
                    
                    if ( $current_version < 105){
                        
                        //remove 'bbppu_first_visit' usermetas
                        
                        $wpdb->query( 
                            $wpdb->prepare( 
                                "
                                DELETE FROM $wpdb->usermeta
                                WHERE meta_key = `bbppu_first_visit`
                                "
                                )
                        );
                        
                        //convert old "bbppu_marked_forum_XXX" user meta keys to a global "bbppu_marked_forums" key.
                        
                        $rows = $wpdb->get_results($wpdb->prepare("SELECT user_id,meta_key,meta_value FROM $wpdb->usermeta WHERE meta_key LIKE '%s'",'bbppu_marked_forum_%'));
                        $users_marks = array();
                        
                        //prepare datas
                        foreach($rows as $row){
                            $forum_id = substr($row->meta_key, strlen('bbppu_marked_forum_'));
                            $users_marks[$row->user_id][$forum_id] = $row->meta_value;
                        }

                        //save datas
                        foreach($users_marks as $user_id=>$user_marks){
                            if (update_user_meta($user_id,'bbppu_marked_forums', $user_marks )){
								//remove old datas
                        		$wpdb->query($wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id='%d' AND meta_key LIKE '%s'", $user_id, 'bbppu_marked_forum_%'));
                            }
                        }
                    }
                }

		//update DB version
		update_option($version_db_key, $this->db_version );
	}
        
	function logged_in_user_actions(){

            if(!is_user_logged_in()) return false;

            //set classes for forums, topics, and subforums links.
            add_filter('bbp_get_forum_class', array(&$this,"post_status_class"),10,2);
            add_filter('bbp_get_topic_class', array(&$this,"post_status_class"),10,2);
            add_filter( 'bbp_list_forums', array(&$this,"list_forums_class"),10,2);

            //update forum / topic status
            add_action('bbp_template_after_forums_loop',array(&$this,'update_current_forum_visit_for_user')); //single forum
            add_action('bbp_template_after_topics_loop',array(&$this,'update_current_forum_visit_for_user')); //single forum
            add_action('bbp_template_after_replies_loop',array(&$this,'update_current_topic_read_by'));       //single topic
            
            //save post actions
            add_action( 'bbp_new_topic_pre_extras',array(&$this,"forum_was_read_before_new_topic"));
            add_action( 'bbp_new_topic',array(&$this,"new_topic"),10,4 );
            add_action( 'save_post',array( $this, 'new_topic_backend' ) );

            add_action( 'bbp_new_reply_pre_extras',array(&$this,"forum_was_read_before_new_reply"),10,2);
            add_action( 'bbp_new_reply',array(&$this,"new_reply"),10,5 );
            add_action( 'save_post',array( $this, 'new_reply_backend' ) );

            //mark as read
            add_action( 'bbp_template_before_forums_index', array(&$this,'mark_as_read_link'));
            add_action( 'bbp_template_before_single_forum', array(&$this,'mark_as_read_link'));
        
        
        
            add_action("wp", array(&$this,"process_mark_as_read"));    //process "mark as read" link
	}
     
	/**
         * When a user visits a single forum, save the time of that visit so we can compare later
         * @param type $user_id
         * @param type $forum_id
         * @return boolean 
         */
         
        //TO FIX check if can be replaced by update_forum_visit_for_user(); so we can remove this function.
            
	function update_current_forum_visit_for_user(){
            global $wp_query;

            /*do not be too strict in the conditions since it makes BuddyPress group forums ignore the function eg. is_single(), etc.*/

            return $this->update_forum_visit_for_user();
	}
        
	function update_forum_visit_for_user($forum_id=false,$user_id=false){
		
            //validate user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;


            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if (get_post_type( $forum_id )!=bbp_get_forum_post_type()) return false;

            $user_meta_key = 'bbppu_forums_visits';
            $visits = $this->get_forums_visits_for_user($user_id);
            $visits[$forum_id] = current_time('timestamp');
            ksort($visits);
            
            self::debug_log("update_forum_visit_for_user: forum#".$forum_id.", user#".$user_id);

            return update_user_meta( $user_id, $user_meta_key, $visits );
	}
        
	function register_scripts_styles(){
            wp_register_style('font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css',false,'4.3.0');
            wp_register_style('bbppu', $this->plugin_url . '_inc/css/bbppu.css',false,$this->version);
            wp_register_script('bbppu', $this->plugin_url . '_inc/js/bbppu.js',array('jquery'),$this->version);
	}
	function scripts_styles(){
	
            //SCRIPTS

            wp_enqueue_script('bbppu');

            //localize vars
            $localize_vars=array();
            $localize_vars['ajaxurl']=admin_url( 'admin-ajax.php' );
            $localize_vars['marked_as_read']=__('Marked as read','bbppu');


            wp_localize_script('bbppu','bbppuL10n', $localize_vars);

            //STYLES
            wp_enqueue_style('font-awesome');
            wp_enqueue_style('bbppu');
	}
    
    function is_bbppu_admin(){
        $screen = get_current_screen();
        if( $screen->id == 'settings_page_bbppu') return true;
    }
    
	function scripts_styles_admin(){
        
            if ( !$this->is_bbppu_admin() ) return;
	
            //SCRIPTS

            //STYLES
            wp_enqueue_style('font-awesome');
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
            
            $show_link = true;
            
            
            //this forum or its ancestor have been marked has read
            if ( $forum_time_marked = self::get_forum_marked_time($forum_id,$user_id) ){
                $last_active_time = bbp_convert_date(get_post_meta( $forum_id, '_bbp_last_active_time', true )); //get post last activity time
                $show_link = ($last_active_time > $forum_time_marked);
            }
            
            //if (!$show_link){ //no new activity since marked
            //    $link_html = __('No new activity since you have marked this forum','bbppu');
            //}else{
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
            //}
            
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

    function get_marked_forums($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        return get_user_meta($user_id, 'bbppu_marked_forums',true);
    }

    /*
     * Marks forum as "read".
     * Remove marks for descendants if any.
     */
        
	public function mark_single_forum_as_read($forum_id) {

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

            return update_user_meta($user_id, 'bbppu_marked_forums', $marked_forums );
	}
        
	/**
	 * Before saving a new post/reply,
	 * store the forum status (read/unread) so we can update its status after the post creation
	 * (see fn 
	 * @param type $post_id
	 * @param type $forum_id
	 */
	function forum_was_read_before_new_topic($forum_id){
            $this->forum_was_read_before_new_post = self::has_user_read($forum_id);
	}
        
	function forum_was_read_before_new_reply($topic_id, $forum_id){
            $this->forum_was_read_before_new_post = self::has_user_read($forum_id);
	}
        
	/**
	 * Runs when a new topic is posted.
	 * @param type $topic_id
	 * @param type $forum_id
	 * @param type $anonymous_data
	 * @param type $topic_author 
	 */
	
	function new_topic($topic_id, $forum_id, $anonymous_data=false, $topic_author=false){
            //delete metas for users who had that post read
            $this->update_topic_read_by($topic_id,$topic_author,true);

            if($this->forum_was_read_before_new_post){
                    self::update_forum_visit_for_user($forum_id,$topic_author);
            }
	}
        
	function new_topic_backend($post_id){
		
            // Bail if doing an autosave
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
                            return $post_id;
            // Bail if not a post request
            if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) )
                            return $post_id;
            // Bail if post_type do not match
            if (get_post_type( $post_id )!=bbp_get_topic_post_type())
                            return;
            // Bail if current user cannot edit this post
            $post_obj = get_post_type_object( get_post_type( $post_id ) ); 
            if ( !current_user_can( $post_obj->cap->edit_post, $post_id ) )
                            return $post_id;

            //get topic id (even if it's a reply)
            $topic_id = bbp_get_topic_id($post_id);
            $forum_id = bbp_get_topic_forum_id( $topic_id );

            $this->new_topic($topic_id,$forum_id);
	}
        
	/**
         * Runs when a new reply is posted.
         * @param type $reply_id
         * @param type $topic_id
         * @param type $forum_id
         * @param type $anonymous_data
         * @param type $reply_author 
         */
	function new_reply($reply_id, $topic_id, $forum_id, $anonymous_data=false, $reply_author=false){
            //delete metas for users who had that post read
            $this->update_topic_read_by($topic_id,$reply_author,true);

            if($this->forum_was_read_before_new_post){
                    self::update_forum_visit_for_user($forum_id,$reply_author);
            }
		
	}
        
	function new_reply_backend($post_id){
        
            if ( !is_admin() ) return;
        
            // Bail if doing an autosave
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;
            // Bail if not a post request
            if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) ) return $post_id;
        
            // Bail if post_type do not match
            if (get_post_type( $post_id )!=bbp_get_reply_post_type()) return;
        
            // Bail if current user cannot edit this post
            $post_obj = get_post_type_object( get_post_type( $post_id ) ); 
            if ( !current_user_can( $post_obj->cap->edit_post, $post_id ) ) return $post_id;
        
            $reply_id = bbp_get_reply_id($post_id);
                    
            if (isset($_POST['post_parent']))
                    $topic_id = bbp_get_topic_id($_POST['post_parent']);
            if (isset($_POST['bbp_forum_id']))
                    $forum_id = bbp_get_forum_id($_POST['bbp_forum_id']);
            $this->new_reply($reply_id,$topic_id,$forum_id);
	}
	
	function update_current_topic_read_by(){
            /*do not be too strict in the conditions since it makes BuddyPress group forums ignore the function eg. is_single(), etc.*/
            if (get_post_type( )!=bbp_get_topic_post_type()) return false;
            return $this->update_topic_read_by();
	}
	function update_topic_read_by($topic_id=false,$user_id=false,$reset=false){
            
            //validate user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

            $read_by_uid = array($user_id);
            
            //get topic id (even if it's a reply)
            $topic_id = bbp_get_topic_id($topic_id);
            
            //check topic
            if (get_post_type( $topic_id )!=bbp_get_topic_post_type()) return false;
            
            $readers = (array)self::get_topic_readers($topic_id);
            
            //if reset is not enabled, clone previous readers.
            if( (!$reset) && ( false !== $readers ) ){
                    $read_by_uid = array_merge($readers,$read_by_uid);
                    $read_by_uid = array_unique($read_by_uid);//remove duplicates
            }

            return update_post_meta( $topic_id, $this->topic_read_metaname, $read_by_uid );
	}
	/**
	* Get the time of the last visit of the user for each forum.
	* @param type $forum_id
	* @param type $user_id
	* @return boolean
	*/
	function get_single_forum_visit_for_user($forum_id=false,$user_id=false){
            
            //validate user
            if(!$user_id) $user_id = get_current_user_id();
            if (!$user_meta = get_userdata( $user_id )) return false;
            
            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if(!$forum_id) return false;
            $visits = self::get_forums_visits_for_user($user_id);
            if ((is_array($visits))&&(array_key_exists($forum_id, $visits))) {
                    return $visits[$forum_id];
            }else{//forum has never been visited before, return registration time
                return $user_meta->user_registered;
            }
	}
	function get_forums_visits_for_user($user_id=false){
            //validate user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

            $visits = get_user_meta($user_id,'bbppu_forums_visits', true );

            return $visits;
	}

	function post_status_class($classes,$post_id){
            
            //TO FIX check allowed post types
            
            $post_type = get_post_type( $post_id );
            $is_read = $this->has_user_read($post_id);
            
            $classes[]='bbppu-hentry';
            
            if (!$is_read){
                    $classes[]='bbppu-unread';
            }else{
                    $classes[]='bbppu-read';
            }
            
            return $classes;


	}
        
        /*
         * Check if the forum (or forum ancestor) has been set as read
         */
        
        function get_forum_marked_time($post_id = null, $user_id = null, $include_ancestors = true){
            
            //validate user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;
            
            //no marks for this user
            if ( !$usermarks = self::get_marked_forums($user_id) ) return;
            
            $ancestormarks = array();

            //get forum ID
            $forum_id  = self::get_forum_id_for_post( $post_id );
            
            $ids_to_check = array($forum_id);

            if ($include_ancestors){  //check whole ancestors tree
                $ids_to_check = array_merge( bbp_get_forum_ancestors( $forum_id ), $ids_to_check);
            }

            foreach ( $usermarks as $marked_id => $marked_time ){
                if ( in_array($marked_id, $ids_to_check) ) $ancestormarks[$marked_id] = $marked_time;
            }

            if (empty($ancestormarks)) {
                return false;
            }else{
                return max($ancestormarks); //get last mark
            }

        }

        /*
         * Gets the forum ID, no matter what the post type is
         * Should be replaced when this ticket is fixed :
         * https://bbpress.trac.wordpress.org/ticket/2769#ticket
         */
        
        function get_forum_id_for_post($post_id = null){
            switch ( get_post_type( $post_id ) ) {
                // Reply
                case bbp_get_reply_post_type() :
                    $forum_id = bbp_get_reply_forum_id( $post_id );
                    break;

                // Topic
                case bbp_get_topic_post_type() :
                    $forum_id = bbp_get_topic_forum_id( $post_id );
                    break;

                // Forum
                case bbp_get_forum_post_type() :
                    $forum_id = $post_id;
                    break;

                // Unknown
                default :
                    $forum_id = $post_id;
                    break;
            }
            
            return bbp_get_forum_id($forum_id);
            
        }
        
        function has_user_read($post_id,$user_id=false){ 
            
            $has_read = false;
            
            //validate user
            if(!$user_id) $user_id = get_current_user_id();
            if (!$user_meta = get_userdata( $user_id )) return;

            //validate forum
            $forum_id = self::get_forum_id_for_post($post_id);
            if ($forum_id === false) return;
            
            $post_type = get_post_type($post_id);
            $last_active_time = bbp_convert_date(get_post_meta( $post_id, '_bbp_last_active_time', true )); //get post last activity time
            
            //this post has been created before user's first visit
            if ( (!$has_read) && ( $this->get_options('test_registration_time') == 'on' ) && ( $first_visit = $user_meta->user_registered ) ){
                $has_read = ($last_active_time <= $first_visit);
            }
            
            //this forum or its ancestor have been marked has read
            if ((!$has_read) && ($forum_time_marked = self::get_forum_marked_time($forum_id,$user_id))){
                $has_read = ($last_active_time <= $forum_time_marked);
            }
            
            if (!$has_read){
                
                switch($post_type){

                    case bbp_get_topic_post_type(): //topic

                        $readers = self::get_topic_readers($post_id);

                        if ( false === $readers ){ //if the plugin was enabled, this should never be false but an array
                            $has_read = true;
                            break;
                        }
                        //check this topic has been read by the logged user
                        $has_read = in_array($user_id,(array)$readers);

                    break;

                    case bbp_get_forum_post_type(): //forum
                        
                        //the forum has neither topics nor replies
                        if (!bbp_get_forum_post_count($post_id)){
                            
                            $has_read = true;
                            break;
                            
                        }
                            
                        if ( (bbp_is_forum_category( $post_id )) && ($subforums = bbp_forum_get_subforums($post_id)) ){

                            $subforums_count = count($subforums);
                            $subforums_read = 0;

                            foreach ($subforums as $subforum) {
                                $has_user_read_subforum = $this->has_user_read($subforum->ID);
                                if ($has_user_read_subforum) $subforums_read++;
                            }

                            $has_read = ($subforums_count == $subforums_read);
                            break;
                        }

                        $user_last_visit = self::get_single_forum_visit_for_user($post_id,$user_id);
                        $has_read = ($last_active_time <= $user_last_visit);


                    break;

                }
            }

            
            
            self::debug_log('user#'.$user_id.' has_user_read '.get_post_type($post_id).'#'.$post_id.' : '.(int)$has_read);
            
            return apply_filters('bbppu_has_user_read',$has_read,$post_id,$user_id);
            
        }
        
        /**
         * Get user ids of visitors having read this topic.
         * Returns false if the meta was never set (plugin not yet installed / disabled)
         * Or returns IDs of users having read the topic
         * @param type $topic_id
         * @return false or array
         */
        
        function get_topic_readers($topic_id){
            if (!metadata_exists('post',$topic_id,$this->topic_read_metaname)) return false;
            $user_ids = get_post_meta($topic_id,$this->topic_read_metaname,true);
            return (array)$user_ids;
        }

        
       //filter list forums to add classes to the subforums links.
        //this is a really nasty hack. 
        //Should be fixed when bbpress fix this ticket :
        //https://bbpress.trac.wordpress.org/ticket/2759#ticket
        
        function list_forums_class($output,$args){
            
            //remove filter to avoid infinite loop
            remove_filter( 'bbp_list_forums', array(&$this,"list_forums_class"), 10 );
            
            //get sub forums
            $sub_forums = bbp_forum_get_subforums( $args['forum_id'] );
            $sub_forums_links = array();
            foreach($sub_forums as $sub_forum){
                $sub_forums_links[$sub_forum->ID] = bbp_get_forum_permalink( $sub_forum->ID );
            }
            
            //get forums list nodes
            $dom = new DOMDocument;
            $dom->loadHTML($output);
            $finder = new DomXPath($dom);
            $classname="bbp-forum-link";
            $forum_link_nodes = $finder->query("//*[contains(@class, '$classname')]");
            
            foreach ($forum_link_nodes as $forum_link_node) {
                $forum_link = $forum_link_node->getAttribute('href');
                $forum_id = array_search($forum_link, $sub_forums_links);
                if ($forum_id){
                    $forum_read = $this->has_user_read($forum_id);
                    $classes = explode(' ',$forum_link_node->getAttribute('class'));
                    $classes = $this->post_status_class($classes,$forum_id);
                    $forum_link_node->setAttribute("class",implode(' ',$classes));
                }
                
            }
            
            //re-add filter
            add_filter( 'bbp_list_forums', array(&$this,"list_forums_class"),10,2);
            
            return $dom->saveHTML();

        }
        
        function debug_log($message) {

            if (WP_DEBUG_LOG !== true) return false;

            $prefix = '[bbppu] : ';
            
            if (is_array($message) || is_object($message)) {
                error_log($prefix.print_r($message, true));
            } else {
                error_log($prefix.$message);
            }
        }

    
    function get_options($keys = null){
        return bbppu_get_array_value($keys,$this->options);
    }
    
    public function get_default_option($keys = null){
        return bbppu_get_array_value($keys,$this->options_default);
    }
    
                
}
/**
 * The main function responsible for returning the one true Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: $pencil_bbp_unread = pencil_bbp_unread();
 *
 * @return The one true Instance
 */
function bbppu() {
	return bbP_Pencil_Unread::instance();
}
bbppu();

?>
