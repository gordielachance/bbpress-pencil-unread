<?php
/*
Plugin Name: bbPress Pencil Unread
Plugin URI: http://wordpress.org/extend/plugins/bbpress-pencil-unread
Description: Display which bbPress forums/topics have already been read by the user.
Author: G.Breant
Version: 1.0.9
Author URI: http://sandbox.pencil2d.org/
License: GPL2+
Text Domain: bbppu
Domain Path: /languages/
*/

class bbP_Pencil_Unread {
	/** Version ***************************************************************/
	
        /**
	 * @public string plugin version
	 */
	public $version = '1.0.9';
        
	/**
	 * @public string plugin DB version
	 */
	public $db_version = '100';
	
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
	 * @public string Prefix for the plugin
	 */
	public $prefix = '';
        
	/**
	 * @public name of the var used for plugin's actions
	 */
	public $action_varname = '';
        
	/**
	 * @public IDs of the forums and their last visit time for the current user. 
         * Stored because we access it(has_user_read_forum) after having updated (update_forum_visit_for_user) it.
	 */
	public $cuser_forums_visits = array();
        
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
                $this->prefix = 'bbppu';
                $this->action_varname = $this->prefix.'_action';
	}
        
	function includes(){
            require( $this->plugin_dir . 'bbppu-template.php');
            require( $this->plugin_dir . 'bbppu-ajax.php');
            if (is_admin()){
            }
	}
	
	function setup_actions(){
            
            /*actions are hooked on bbp hooks so plugin will not crash if bbpress is not enabled*/

            //localization
            add_action('bbp_init', array($this, 'load_plugin_textdomain'));

            //upgrade
            add_action('bbp_loaded', array($this, 'upgrade'));

            add_action('bbp_init', array($this, 'register_scripts_styles'));
            add_action('bbp_enqueue_scripts', array($this, 'scripts_styles'));
            add_action('bbp_init',array(&$this,"logged_in_user_actions"));
	}
        
	public function load_plugin_textdomain(){
		load_plugin_textdomain($this->prefix, FALSE, $this->plugin_dir.'languages/');
	}
        
	function upgrade(){
		global $wpdb;
		
		$version_db_key = $this->prefix.'-db-version';
		
		$current_version = get_option($version_db_key);
		
		
		if ($current_version==$this->db_version) return false;
			
		//install
		/*
		if(!$current_version){
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			//dbDelta($sql);
		}
		 */
		//update DB version
		update_option($version_db_key, $this->db_version );
	}
        
	function logged_in_user_actions(){
            if(!is_user_logged_in()) return false;

            add_filter('bbp_get_forum_class', array(&$this,"forum_status_class"),10,2);
            add_filter('bbp_get_topic_class', array(&$this,"topic_status_class"),10,2);

            //save first visit (ever) time.  Older content will be tagged as "read".
            add_action('bbp_template_before_forums_loop',array(&$this,'save_user_first_visit'));
            add_action('bbp_template_before_topics_loop',array(&$this,'save_user_first_visit'));
            add_action('bbp_template_before_replies_loop',array(&$this,'save_user_first_visit'));


            //single forum
            add_action('bbp_template_after_topics_loop',array(&$this,'update_current_forum_visit_for_user'));

            //single topic
            add_action('bbp_template_after_replies_loop',array(&$this,'update_current_topic_read_by'));
            //saving
            add_action( 'bbp_new_topic_pre_extras',array(&$this,"forum_was_read_before_new_topic"));
            add_action( 'bbp_new_topic',array(&$this,"new_topic"),10,4 );
            add_action( 'save_post',array( $this, 'new_topic_backend' ) );

            add_action( 'bbp_new_reply_pre_extras',array(&$this,"forum_was_read_before_new_reply"),10,2);
            add_action( 'bbp_new_reply',array(&$this,"new_reply"),10,5 );
            add_action( 'save_post',array( $this, 'new_reply_backend' ) );


            //mark as read
            add_action('bbp_template_after_pagination_loop', array(&$this,"mark_as_read_single_forum_link"));
            //add_action('bbp_template_before_forums_loop', array(&$this,"mark_as_read_forums_link"));
            add_action("wp", array(&$this,"check_link_mark_as_read"));
	}
        
	/*
         * Save the user first visit time, so past topics / replies can be set to read even if they are not.
         * TO FIX date checked should be registration time ?
         */
        
	function save_user_first_visit(){
		
            $user_id = get_current_user_id();
            if(!$user_id) return false;

            $first_visit = self::get_user_first_visit($user_id);
            if($first_visit) return false;

            $time = current_time('timestamp');
            update_user_meta($user_id,$this->prefix.'_first_visit', $time );
	}
        
		function get_user_first_visit($user_id){
                    $user_id = get_current_user_id();
                    if(!$user_id) return false;
                    return get_user_meta($user_id,$this->prefix.'_first_visit',true);
		}
            
	/**
         * When a user visits a single forum, save the time of that visit so we can compare later
         * @param type $user_id
         * @param type $forum_id
         * @return boolean 
         */
            
	function update_current_forum_visit_for_user(){
            global $wp_query;

            /*do not be too strict in the conditions since it makes BuddyPress group forums ignore the function eg. is_single(), etc.*/

            return $this->update_forum_visit_for_user();
	}
        
	function update_forum_visit_for_user($forum_id=false,$user_id=false){
		
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;


            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if (get_post_type( $forum_id )!=bbp_get_forum_post_type()) return false;

            $user_meta_key = $this->prefix.'_forums_visits';
            $visits = $this->get_forums_visits_for_user($user_id);
            $visits[$forum_id] = current_time('timestamp');

            return update_user_meta( $user_id, $user_meta_key, $visits );
	}
        
	function register_scripts_styles(){
            wp_register_style($this->prefix, $this->plugin_url . '_inc/bbppu.css',false,$this->version);
            wp_register_script($this->prefix, $this->plugin_url . '_inc/js/bbppu.js',array('jquery'),$this->version);
	}
	function scripts_styles(){
	
            //SCRIPTS

            wp_enqueue_script($this->prefix);

            //localize vars
                            $localize_vars=array();
                            $localize_vars['marked_as_read']=__('Marked as read','bbppu');


            wp_localize_script($this->prefix,$this->prefix.'L10n', $localize_vars);

            //STYLES
            wp_enqueue_style($this->prefix);
	}
        
	function mark_as_read_single_forum_link($forum_id=false){
            if(!is_single()) return false;
            if( get_post_type()!=bbp_get_forum_post_type() ) return false;
            $url = get_permalink();
            $forum_id = bbp_get_forum_id($forum_id);
            $nonce_action = 'bbpu_mark_read_single_forum_'.$forum_id;
            $nonce = wp_create_nonce($nonce_action);
            $url = add_query_arg(array('action'=>'bbpu_mark_read'),$url);
            $url = wp_nonce_url( $url,$nonce_action);
            ?>
            <div class="bbppu-mark-as-read">
                    <a href="<?php echo $url;?>" data-nonce="<?php echo $nonce;?>" data-forum-id="<?php echo $forum_id;?>"><?php _e('Mark all as read','bbppu');?></a>
            </div>
            <?php
	}
	// processes the mark as read action
	public function check_link_mark_as_read() {
            global $post;
            if( !isset( $_GET['action'] ) || $_GET['action'] != 'bbpu_mark_read' )return false;
            if(is_single() && (get_post_type( $post->ID )==bbp_get_forum_post_type())){ //single forum
                    $forum_id = bbp_get_forum_id($post->ID);
                    if( ! wp_verify_nonce( $_GET['_wpnonce'], 'bbpu_mark_read_single_forum_'.$forum_id ) ) return false;
                    self::mark_single_forum_as_read($forum_id);
            }
	}
	public function mark_single_forum_as_read($forum_id) {
            $user_id = get_current_user_id();
            if(!$user_id) return false;
            $meta_key_name = $this->prefix.'_marked_forum_'.$forum_id;
            $time = current_time('timestamp');
            return update_user_meta($user_id, $meta_key_name, $time );
	}
        
	/**
	 * Before saving a new post/reply,
	 * store the forum status (read/unread) so we can update its status after the post creation
	 * (see fn 
	 * @param type $post_id
	 * @param type $forum_id
	 */
	function forum_was_read_before_new_topic($forum_id){
            $this->forum_was_read_before_new_post = self::has_user_read_forum($forum_id);
	}
        
	function forum_was_read_before_new_reply($topic_id, $forum_id){
            $this->forum_was_read_before_new_post = self::has_user_read_forum($forum_id);
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
            // Bail if doing an autosave
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
                            return $post_id;
            // Bail if not a post request
            if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) )
                            return $post_id;
            // Bail if post_type do not match
            if (get_post_type( $post_id )!=bbp_get_reply_post_type())
                            return;
            // Bail if current user cannot edit this post
            $post_obj = get_post_type_object( get_post_type( $post_id ) ); 
            if ( !current_user_can( $post_obj->cap->edit_post, $post_id ) )
                            return $post_id;
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
            //get topic id (even if it's a reply)
            $topic_id = bbp_get_topic_id($topic_id);
            //check forum
            if (get_post_type( $topic_id )!=bbp_get_topic_post_type()) return false;
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;
            $meta_key_name = $this->prefix.'_read_by';
            if(!$reset){
                    $read_by_uid = get_post_meta( $topic_id, $meta_key_name, true );
            }

            $forum_id = bbp_get_topic_forum_id($topic_id);
            $this->update_forum_visit_for_user();

            //remove duplicates
            $read_by_uid[]=$user_id;
            $read_by_uid = array_unique($read_by_uid);
            return update_post_meta( $topic_id, $meta_key_name, $read_by_uid );
	}
	/**
	* Get the time of the last visit of the user for each forum.
	* At the first call, value is stored in $this->cuser_forums_visits for the current user,
	* so it's the "old" value is not erased when calling update_forum_visit_for_user.
	* @param type $forum_id
	* @param type $user_id
	* @return boolean
	*/
	function get_single_forum_visit_for_user($forum_id=false,$user_id=false){
            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if(!$forum_id) return false;
            $visits = self::get_forums_visits_for_user($user_id);
            if ((is_array($visits))&&(array_key_exists($forum_id, $visits))) {
                    return $visits[$forum_id];
            }else{//forum has never been visited before, return first visit time
                    return self::get_user_first_visit($user_id);
            }
	}
	function get_forums_visits_for_user($user_id=false){
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;
            $user_meta_key = $this->prefix.'_forums_visits';
            //if (($user_id==get_current_user_id())&&($this->cuser_forums_visits)) { //use the value already stored
                //  $meta = $this->cuser_forums_visits;
            //}else{
                    $visits = get_user_meta($user_id,$user_meta_key, true );
                //  if ($user_id==get_current_user_id()){
                            //$this->cuser_forums_visits = $meta;
                    //}
            //}
            return $visits;
	}
	function get_user_last_forum_visit($forum_id,$user_id=false){
            $last_visit = $this->get_single_forum_visit_for_user($forum_id,$user_id);
            return $last_visit;
	}
	function topic_status_class($classes,$topic_id){

            $is_read = $this->has_user_read_topic($topic_id);

            if (!$is_read){
                    $classes[]=$this->prefix.'-unread';
            }else{
                    $classes[]=$this->prefix.'-read';
            }
            return $classes;
	}
	function forum_status_class($classes,$forum_id){
            $is_read = $this->has_user_read_forum($forum_id);
            if (!$is_read){
                    $classes[]=$this->prefix.'-unread';
            }else{
                    $classes[]=$this->prefix.'-read';
            }
            return $classes;
	}
	
	
	function has_user_read_topic($topic_id,$user_id=false){ 
            $has_read = false;

            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

            //validate topic
            $topic_id = bbp_get_topic_id($topic_id);
            if (get_post_type( $topic_id )!=bbp_get_topic_post_type()) return false;

            $forum_id = bbp_get_topic_forum_id($topic_id);
            $topic_last_active_time = bbp_convert_date(get_post_meta( $topic_id, '_bbp_last_active_time', true ));


            $forum_marked_key_name = $this->prefix.'_marked_forum_'.$forum_id;
            $forum_time_marked = get_user_meta($user_id, $forum_marked_key_name, true );
            if($forum_time_marked){  //check forum has been marked as read
                    $has_read = ($topic_last_active_time <= $forum_time_marked);
            }

            if (!$has_read){ //check topic activity against user first visit
                    $first_visit = self::get_user_first_visit($user_id);
                    $has_read = ($topic_last_active_time <= $first_visit);
            }

            if (!$has_read){
                    //TOPIC READ CHECK
                    $meta_key_name = $this->prefix.'_read_by';
                    //if the key was never set
                    //(topic was created before the plugin was installed)
                    //considerate as read

                    if (!metadata_exists('post',$topic_id,$meta_key_name)){
                            $has_read = true;
                    }else{
                            $user_ids = get_post_meta($topic_id,$meta_key_name,true);
                            $has_read = in_array($user_id,(array)$user_ids);
                    }
            }


            return apply_filters('bbppu_user_has_read_topic',$has_read,$topic_id,$user_id);
	} 
        
	function has_user_read_forum($forum_id,$user_id=false){

            $has_read = false;

            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if (get_post_type( $forum_id )!=bbp_get_forum_post_type()) return false;

            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

            //if forum is empty, set to true
            $post_count = bbp_get_forum_post_count($forum_id);
            if(!$post_count){
                    $has_read = true;
            }else{
                    $user_last_visit = $this->get_user_last_forum_visit($forum_id,$user_id);
                    $forum_last_active_time = bbp_convert_date(get_post_meta( $forum_id, '_bbp_last_active_time', true ));
                    $has_read = ($forum_last_active_time <= $user_last_visit);
            }

            return apply_filters('bbppu_user_has_read_forum',$has_read,$forum_id,$user_id);
	}
	function classes_attr($classes=false){
            if (!$classes) return false;
            return ' class="'.implode(" ",(array)$classes).'"';	
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
function bbp_pencil_unread() {
	return bbP_Pencil_Unread::instance();
}
bbp_pencil_unread();
?>         }

            }

            
            
            return apply_filters('bbppu_user_has_read_topic',$has_read,$topic_id,$user_id);
        } 

        
        function has_user_read_forum($forum_id,$user_id=false){
            
            $has_read = false;
            
            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if (get_post_type( $forum_id )!=bbp_get_forum_post_type()) return false;
            
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

                
            //if forum is empty, set to true
            $post_count = bbp_get_forum_post_count($forum_id);
            if(!$post_count){

                $has_read = true;

            }else{

                $user_last_visit = $this->get_user_last_forum_visit($forum_id,$user_id);
                $forum_last_active_time = bbp_convert_date(get_post_meta( $forum_id, '_bbp_last_active_time', true ));
                $has_read = ($forum_last_active_time <= $user_last_visit);
            }
            
            return apply_filters('bbppu_user_has_read_forum',$has_read,$forum_id,$user_id);
        }

        function classes_attr($classes=false){
            if (!$classes) return false;
            return ' class="'.implode(" ",(array)$classes).'"';
            
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

function bbp_pencil_unread() {
	return bbP_Pencil_Unread::instance();
}

bbp_pencil_unread();

?>
