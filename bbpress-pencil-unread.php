<?php
/*
Plugin Name: bbPress Pencil Unread
Plugin URI: http://wordpress.org/extend/plugins/bbpress-pencil-unread
Description: Display which bbPress forums/topics have already been read by the user.
Author: G.Breant
Author URI: https://profiles.wordpress.org/grosbouff/
Text Domain: bbppu
Version: 1.3.2

*/

class bbP_Pencil_Unread {
	/** Version ***************************************************************/
	
        /**
	 * @public string plugin version
	 */
	public $version = '1.3.2';
        
	/**
	 * @public string plugin DB version
	 */
	public $db_version = '108';
	
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
	 * meta keys
	 */
    public $options_metaname = 'bbppu_options'; // plugin's options (stored in wp_options) 
	public $topic_readby_metaname = 'bbppu_read_by'; // contains an array of user IDs having read that post (stored in postmeta)

    public $qvar = 'bbppu';
    
    public $donate_link = 'http://bit.ly/gbreant';
    
    public $query_time = null;

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
            'forums_marks'                          => 'on',
            'test_registration_time'                => 'on', //all items dated befoe user's first visit
            'bookmarks'                             => 'on',
            'has_read_cache_time'                   => 5,
            
        );
        
        $this->options = wp_parse_args(get_option( $this->options_metaname), $this->options_default);
        
        
	}
        
	function includes(){
            require( $this->plugin_dir . 'bbppu-functions.php');
            require( $this->plugin_dir . 'bbppu-settings.php');
            require( $this->plugin_dir . 'bbppu-template.php');
            //require( $this->plugin_dir . 'bbppu-ajax.php');
        
            if ( $this->get_options('forums_marks') == 'on' ){
                require( $this->plugin_dir . 'bbppu-forum-marks.php');
            }
        
            if ( $this->get_options('bookmarks') == 'on' ){
                require( $this->plugin_dir . 'bbppu-bookmarks.php');
            }
        
            if (is_admin()){
            }
	}
	
	function setup_actions(){
            
            /*actions are hooked on bbp hooks so plugin will not crash if bbpress is not enabled*/

            add_action('bbp_init', array($this, 'load_plugin_textdomain'));     //localization - WP hook would be 'after_setup_theme'
            add_action('bbp_loaded', array($this, 'upgrade'));                  //upgrade
            
            add_action('bbp_init',array(&$this,"logged_in_user_actions"));      //logged in users actions

            //scripts & styles
            add_action('bbp_init', array($this, 'register_scripts_styles'));
            add_action('bbp_enqueue_scripts', array($this, 'scripts_styles'));
            add_action('admin_enqueue_scripts', array($this, 'scripts_styles_admin'));
        
            add_filter( 'plugin_action_links_' . $this->basename, array($this, 'plugin_bottom_links')); //bottom links
        
            add_action( 'bbp_footer', array($this, 'debug_msg_total_queries_time'));
            
	}
    
    function plugin_bottom_links($links){
        
        $links[] = sprintf('<a target="_blank" href="%s">%s</a>',$this->donate_link,__('Donate','bbppu'));//donate
        
        if (current_user_can('manage_options')) {
            $settings_page_url = add_query_arg(
                array(
                    'page'=>bbP_Pencil_Unread_Settings::$menu_slug
                ),
                get_admin_url(null, 'options-general.php')
            );
            $links[] = sprintf('<a href="%s">%s</a>',esc_url($settings_page_url),__('Settings'));
        }
        
        return $links;
    }
        
	public function load_plugin_textdomain(){
		load_plugin_textdomain( 'bbppu', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
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
                        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key = %s",'bbppu_first_visit') );
                        
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
            
                    if ( $current_version < 107){

                        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE meta_key = '%s'",$this->topic_readby_metaname));
                        
                        //remove old metas (unique arrays of IDs)
                        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s",$this->topic_readby_metaname) );
                        
                        //create new metas (one meta per user ID)
                        foreach((array)$rows as $row){
                            $post_id = $row->post_id;
                            $user_ids = maybe_unserialize($row->meta_value);
                            
                            foreach((array)$user_ids as $user_id){
                                add_post_meta($post_id,$this->topic_readby_metaname,$user_id);
                            }
                        }                        
                        
                    }
            
                    //remove 'bbppu_forums_visits' usermetas
                    //TO FIX could be done for v1.2.3, but wait a little that we will not revert that stuff enabling removing it.
                    //$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key = %s",'bbppu_forums_visits') );
                    
            
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
            add_action('bbp_template_after_replies_loop',array(&$this,'update_current_topic_read_by'));       //single topic

            //save post actions
            add_action( 'bbp_new_topic',array(&$this,"new_topic"),10,4 );
            add_action( 'save_post',array( $this, 'new_topic_backend' ) );

            add_action( 'bbp_new_reply',array(&$this,"new_reply"),10,5 );
            add_action( 'save_post',array( $this, 'new_reply_backend' ) );

            //queries
            add_filter('query_vars', array(&$this,'register_query_vars' ));
            add_action( 'pre_get_posts', array($this, 'filter_query'));
        
	}
    
    function register_query_vars($vars) {
        $vars[] = $this->qvar;
        return $vars;
    }

    // Filter a query for read/unread posts if the 'bbppu' var is set
    function filter_query( $query ){
        
        if ( ( $query_var = $query->get( $this->qvar ) ) && ( $user_id = get_current_user_id() ) ){
            
            $meta_query = $query->get('meta_query');
            
            switch($query_var){
                case 'read':
                    
                    $meta_query[] = array(
                      'key' => $this->topic_readby_metaname,
                      'value' => $user_id,
                      'compare' => '=',
                    );
                    
                break;
                case 'unread':
                    
                    $meta_query[] = array(
                      'key' => $this->topic_readby_metaname,
                      'value' => $user_id,
                      'compare' => '!=',
                    );
                    
                break;
            }
            
            $query->set('meta_query', $meta_query);
            
        }
        return $query;
        
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
    
    //checks if the current page is the bbppu settings page
    function is_bbppu_admin(){
        $screen = get_current_screen();
        if( $screen && ($screen->id == 'settings_page_bbppu') ) return true;
    }
    
	function scripts_styles_admin(){
        
            if ( !$this->is_bbppu_admin() ) return;
	
            //SCRIPTS

            //STYLES
            wp_enqueue_style('font-awesome');
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
	 * Runs when a new topic is posted.
	 * @param type $topic_id
	 * @param type $forum_id
	 * @param type $anonymous_data
	 * @param type $topic_author 
	 */
	
	function new_topic($topic_id, $forum_id, $anonymous_data=false, $topic_author=false){
            $this->update_topic_read_by($topic_id,$topic_author);
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
            $this->reset_topic_read_by($topic_id,$reply_author);
            $this->update_topic_read_by($topic_id,$reply_author);

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
            $topic_id = (isset($_POST['post_parent'])) ? bbp_get_topic_id($_POST['post_parent']) : null;
            $forum_id =  (isset($_POST['bbp_forum_id']))  ? bbp_get_forum_id($_POST['bbp_forum_id']) : null;
        
            $this->new_reply($reply_id,$topic_id,$forum_id);
	}
	
	function update_current_topic_read_by(){
            /*do not be too strict in the conditions since it makes BuddyPress group forums ignore the function eg. is_single(), etc.*/
            if (get_post_type( )!=bbp_get_topic_post_type()) return false;
            return $this->update_topic_read_by();
	}
    
    function reset_topic_read_by($topic_id=false){
            //get topic id (even if it's a reply)
            $topic_id = bbp_get_topic_id($topic_id);
            return delete_post_meta($topic_id, $this->topic_readby_metaname);
    }
    
	function update_topic_read_by($topic_id=false,$user_id=false){
            
            //validate user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

            //get topic id (even if it's a reply)
            $topic_id = bbp_get_topic_id($topic_id);
        
            $read_by = $this->get_topic_readers($topic_id);
        
            //check topic
            if (get_post_type( $topic_id )!=bbp_get_topic_post_type()) return false;
        
            if ( $read_by && in_array($user_id,$read_by) ) return true; //already set

            return add_post_meta( $topic_id, $this->topic_readby_metaname, $user_id );
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
     * Gets the forum ID, no matter what the post type is
     * Should be replaced when this ticket is fixed :
     * https://bbpress.trac.wordpress.org/ticket/2769#ticket
     */

    function get_forum_id_for_post($post_id = null){
        switch ( get_post_type( $post_id ) ) {

            // Forum
            case bbp_get_forum_post_type() :
                $forum_id = $post_id;
                break;

            // Topic
            case bbp_get_topic_post_type() :
                $forum_id = bbp_get_topic_forum_id( $post_id );
                break;

            // Reply
            case bbp_get_reply_post_type() :
                $forum_id = bbp_get_reply_forum_id( $post_id );
                break;

            // Unknown
            default :
                $forum_id = $post_id;
                break;
        }

        return bbp_get_forum_id($forum_id);

    }

    function has_user_read_all_forum_topics( $forum_id,$user_id=false ){
        
        //check : http://wordpress.stackexchange.com/questions/239603/how-to-speed-up-a-complex-wp-query

        if(!$user_id) $user_id = get_current_user_id();
        if(!$user_id) return true;
        
        $time_start = microtime( true ); //for debug
        $debug_transient_message = ' (from cache)';
        $topics_total = '-';
        
        //TO FIX TO CHECK use wp_cache_set instead of transients ?
        $transient_name = sprintf('bbppu_has_read_%s_user_%s',$forum_id,$user_id);
        $transient_duration = $this->get_options('has_read_cache_time');

        if ( (!$transient_duration) || ( false === ( $has_read = get_transient( $transient_name ) ) ) ) {

            $time_start = microtime( true ); //for debug
            $debug_transient_message = '';

            //count topics - check bbPress function bbp_has_topics()

            $posts_per_page = get_option('posts_per_page');

            $topics_args = array(
                'post_type'         => bbp_get_topic_post_type(),
                'post_parent'       => $forum_id,
                'posts_per_page'    => -1,
                //optimize query :
                'fields'            => 'ids',
                'no_found_rows' => true, //https://wpartisan.me/tutorials/wordpress-database-queries-speed-sql_calc_found_rows
                'update_post_term_cache' => false, // grabs post terms
                'update_post_meta_cache' => true // grabs post meta (here needed)
            );

            if ( $skip_timestamp = $this->get_skip_timestamp($user_id,$forum_id) ){
                $skip_time = date_i18n( 'Y-m-d H:i:s', $skip_timestamp ); //mysql format
                //TO FIX should we query the 'post_date' field instead of this; which slows down the query ?  
                //We must be sure it has the same value than '_bbp_last_active_time' postmeta.
                $topics_args['meta_query'][] = array(
                  'key' => '_bbp_last_active_time',
                  'value' => $skip_time,
                  'compare' => '>',
                );
            }

            // Default view=all statuses
            $post_statuses = array(
                bbp_get_public_status_id(),
                bbp_get_closed_status_id()
                //bbp_get_spam_status_id(),
                //bbp_get_trash_status_id()
            );

            // Add support for private status
            if ( current_user_can( 'read_private_topics' ) ) {
                $post_statuses[] = bbp_get_private_status_id();
            }

            // Join post statuses together
            $topics_args['post_status'] = implode( ',', $post_statuses );

            //count read topics
            $read_topics_args = array(
                $this->qvar => 'read'
            );

            $has_read = true; //assuming we've read the forum 



            //make queries
            $read_topics_args = array_merge($topics_args,$read_topics_args);
            $topics_query = new WP_Query( $topics_args );
            //self::debug_log( $topics_args );
            $read_topics_query = new WP_Query($read_topics_args);

            //compare counts
            if ( $topics_total = count($topics_query->posts) ){

                $read_topics_total = count($read_topics_query->posts);

                self::debug_log( sprintf('topics read : %s/%s',$read_topics_total,$topics_total) );

                //has read ?
                if ($read_topics_total != $topics_total) {
                    $has_read = false;
                }

            }
            
            //store for a few seconds
            set_transient( $transient_name, $has_read, $transient_duration );
            
        }
        
        $query_time = number_format( microtime( true ) - $time_start, 10 );
        self::debug_log(sprintf(' - user#%s has read all %s topics from forum#%s : %s - query time : %s',$user_id,$topics_total,$forum_id,$has_read,$query_time).$debug_transient_message);

        return $has_read;

    }

    /**
        get a 'skip timestamp'.
        below the returned time, ignore the 'unread' state of a post.
    **/

    function get_skip_timestamp($user_id=false,$post_id = false){

        $skip_timestamps = array();

        //validate user
        if(!$user_id) $user_id = get_current_user_id();
        if ( $user_meta = get_userdata( $user_id ) ){

            if ( $this->get_options('test_registration_time') == 'on' ) {
                $skip_timestamps[] = bbp_convert_date( $user_meta->user_registered ); //user registration time
            }

            if ( $this->get_options('forums_marks') == 'on' ) {

                if ( $post_id && ( $forum_mark = bbP_Pencil_Unread_Forum_Marks::get_forum_marked_time($post_id,$user_id) ) ){
                    $skip_timestamps[] = $forum_mark; //'mark as read'
                }
            }

        }

        return max($skip_timestamps);

    }

    function has_user_read($post_id,$user_id=false){ 

        $has_read = false;
        $time_start = microtime( true ); //for debug

        //validate user
        if(!$user_id) $user_id = get_current_user_id();
        if (!$user_meta = get_userdata( $user_id )) return;

        //validate forum
        $forum_id = self::get_forum_id_for_post($post_id);
        if ($forum_id === false) return;

        $post_type = get_post_type($post_id);
        
        self::debug_log( sprintf('CHECK %s %s (has_user_read) for user#%s',get_post_type($post_id),$post_id,$user_id) );

        //skip timestamp
        if ( $skip_timestamp = $this->get_skip_timestamp($user_id,$post_id) ){

            $last_active_time = bbp_convert_date(get_post_meta( $post_id, '_bbp_last_active_time', true )); //get post last activity time
            $has_read = ($skip_timestamp > $last_active_time);

            if ($has_read) self::debug_log( sprintf('skipped : skip_timestamp > last_active_time - %s > %s',$skip_timestamp,$last_active_time) );

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

                    self::debug_log("user belongs to topic's readers");

                break;

                case bbp_get_forum_post_type(): //forum

                    //the forum has neither topics nor replies
                    if (!bbp_get_forum_post_count($post_id)){

                        $has_read = true;

                        self::debug_log('has neither topics nor replies');

                        break;

                    }

                    $check_forums = array($post_id);

                    //forum hierarchy
                    if ( $subforums = bbp_forum_get_subforums($post_id) ){

                        $subforums_count = count($subforums);
                        $subforums_read = 0;

                        foreach ($subforums as $subforum) {
                            $check_forums[] = $subforum->ID;
                        }

                    }

                    //look for an unread forum
                    $has_read = true;
                    foreach($check_forums as $forum_id){
                        if ( !$this->has_user_read_all_forum_topics( $forum_id,$user_id ) ) {
                            $has_read = false;
                            break;
                        }
                    }

                break;

            }

        }

        self::debug_log('HAS READ: '.(int)$has_read);
        self::debug_log('-');
        $query_time = number_format( microtime( true ) - $time_start, 10 );
        $this->query_time += $query_time;

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
        if (!metadata_exists('post',$topic_id,$this->topic_readby_metaname)) return false;
        $user_ids = get_post_meta($topic_id,$this->topic_readby_metaname);
        return (array)$user_ids;
    }


    //TO FIX
    //filter list forums to add classes to the subforums links.
    //this is a really nasty hack. 
    //Should be fixed when bbpress fix this ticket :
    //https://bbpress.trac.wordpress.org/ticket/2760#ticket

    function list_forums_class($output,$args){

        if ( !empty( $output ) && ( $sub_forums = bbp_forum_get_subforums( $args['forum_id'] ) ) ){
            //remove filter to avoid infinite loop
            remove_filter( 'bbp_list_forums', array(&$this,"list_forums_class"), 10 );

            //get sub forums

            $sub_forums_links = array();
            foreach( (array)$sub_forums as $sub_forum){
                $sub_forums_links[$sub_forum->ID] = bbp_get_forum_permalink( $sub_forum->ID );
            }

            //get forums list nodes
            $dom = new DOMDocument;
            $internalErrors = libxml_use_internal_errors(true); // set error level
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $output); // use utf8 encoding to avoid problems with foreign languages (http://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly)
            $finder = new DomXPath($dom);
            $classname="bbp-forum-link";
            $forum_link_nodes = $finder->query("//*[contains(@class, '$classname')]");

            foreach ( $forum_link_nodes as $forum_link_node) {

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

            $output = $dom->saveHTML();

            libxml_use_internal_errors($internalErrors); // restore error level
        }

        return $output;

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
    
    function debug_msg_total_queries_time(){
        self::debug_log("---");
        self::debug_log("TOTAL QUERY TIME:".$this->query_time);
        self::debug_log("---");
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
