<?php
/*
 * Plugin Name: bbPress Pencil Unread
 * Plugin URI: http://wordpress.org/extend/plugins/bbpress-pencil-unread
 * Description: Display which bbPress forums/topics have already been read by the user.
 * Author: G.Breant
 * Version: 1.0.2
 * Author URI: http://sandbox.cargoculte.be/
 * License: GPL2+
 * Text Domain: bbppu
 * Domain Path: /languages/
 */



class bbP_Pencil_Unread {
	/** Version ***************************************************************/

	/**
	 * @public string plugin version
	 */
	public $version = '1.0.2';

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
         * Stored because we access it(has_user_read_forum) after having updated (single_forum_display) it.
	 */
        public $cuser_forums_visits = array();
        
        /**
         * When creating a new post (topic/reply), set this var to true or false
         * So we can update the forum status after the post creation (keep it read if it was read)
         * @var type 
         */
        public $forum_was_read_before_new_post = false;
        
	/**
	 * @var The one true bbPress Unread Posts Instance
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
            require( $this->plugin_dir . 'bbppu-template.php'   );
            
            if (is_admin()){
            }
	}
	
	function setup_actions(){
            
            //localization (nothing to localize yet, so disable it)
            //add_action('init', array($this, 'load_plugin_textdomain'));
            //upgrade
            add_action( 'plugins_loaded', array($this, 'upgrade'));
            
            add_action( 'wp_enqueue_scripts', array( $this, 'scripts_styles' ) );//scripts + styles
            add_action("init",array(&$this,"logged_in_user_actions"));

	}
        
        public function load_plugin_textdomain(){
            load_plugin_textdomain($this->prefix, FALSE, $this->plugin_dir.'/languages/');
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

            //FORUMS
            add_action("wp", array(&$this,"action_mark_forum_as_read"));
            add_action("loop_end", array(&$this,"single_forum_display"));
            add_action('bbppu-single_forum_display',array(&$this,'update_forums_visits_for_user'));
            
            add_filter('bbp_get_forum_class', array(&$this,"forum_status_class"),10,2);
            //add_action("bbp_template_after_topics_loop", array(&$this,"forum_mark_read_link"));
            
            
            //TOPICS
            add_action("wp", array(&$this,"single_topic_display"));
            
            //Adds the current user to the list of users who have read that topic
            add_action('bbppu-single_topic_display',array(&$this,'update_topic_read_by'));
            
            add_filter('bbp_get_topic_class', array(&$this,"topic_status_class"),10,2);

            //saving
            add_action( 'bbp_new_topic_pre_extras',array(&$this,"forum_was_read_before_new_topic"));
            add_action( 'bbp_new_topic',array(&$this,"new_topic"),10,4 );
            
            
            //REPLIES
            add_action("wp", array(&$this,"single_reply_display"));
            
            //Adds the current user to the list of users who have read that topic (which is the reply's parent)
            add_action('bbppu-single_reply_display',array(&$this,'update_topic_read_by'));
            
            //saving
            add_action( 'bbp_new_reply_pre_extras',array(&$this,"forum_was_read_before_new_reply"),10,2);
            add_action( 'bbp_new_reply',array(&$this,"new_reply"),10,5 );

        }

        function scripts_styles(){
            wp_register_style( $this->prefix.'-style', $this->plugin_url . 'bbppu.css' );
            wp_enqueue_style( $this->prefix.'-style' );
        }
        
        function single_forum_display(){
            global $post,$wp_query;

            if (!is_single()) return false;
            if (get_post_type( $post->ID )!=bbp_get_forum_post_type()) return false;

            $forum_id = bbp_get_forum_id($post->ID);

            do_action('bbppu-single_forum_display',$forum_id);
        }

        function single_topic_display(){
            global $post;
            
            if (!is_single()) return false;
            
            if (get_post_type( $post->ID )!=bbp_get_topic_post_type()) return false;

            $topic_id = bbp_get_topic_id($post->ID);

            do_action('bbppu-single_topic_display',$topic_id);

        }
        
        /**
         * Adds the current user to the list of users who have read that topic (which is the reply's parent)
         * @global type $post
         * @return boolean 
         */
        
        function single_reply_display(){
            global $post;
            
            if (!is_single()) return false;

            if (get_post_type( $post->ID )!=bbp_get_reply_post_type()) return false;
            
            $reply_id = bbp_get_reply_id($post->ID);

            do_action('bbppu-single_reply_display',$reply_id);

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
        
        function new_topic($topic_id, $forum_id, $anonymous_data, $topic_author){
            //delete metas for users who had that post read
            $this->update_topic_read_by($topic_id,$topic_author,true);
            
            if($this->forum_was_read_before_new_post){
                self::update_forums_visits_for_user($forum_id,$topic_author);
            }
        }
        
        /**
         * Runs when a new reply is posted.
         * @param type $reply_id
         * @param type $topic_id
         * @param type $forum_id
         * @param type $anonymous_data
         * @param type $reply_author 
         */

        function new_reply($reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author){
            //delete metas for users who had that post read
            $this->update_topic_read_by($topic_id,$reply_author,true);
            
            if($this->forum_was_read_before_new_post){
                self::update_forums_visits_for_user($forum_id,$reply_author);
            }
            
        }
        


        function update_topic_read_by($topic_id,$user_id=false,$reset=false){

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

            //remove duplicates
            $read_by_uid[]=$user_id;
            $read_by_uid = array_unique($read_by_uid);

            return update_post_meta( $topic_id, $meta_key_name, $read_by_uid );

        }
        
        /**
         * When visiting a single forum, set its state to read
         * @return boolean
         */
        
        function action_mark_forum_as_read($forum_id=false){
            
            //get forum id
            $forum_id = bbp_get_forum_id($forum_id);
            
            
            //check this is a single forum
            if (get_post_type( $forum_id )!=bbp_get_forum_post_type()) return false;
            if (!is_single()) return false;
            
            //check an action is set
            if(!isset($_REQUEST[$this->action_varname])) return false;
            $action = $_REQUEST[$this->action_varname];
            
            //check nonce is valid
            $nonce=$_REQUEST['_wpnonce'];
            if (! wp_verify_nonce($nonce, $action) ) return false;

            switch ($action) {
                case 'mark_forum_read':
                    $this->user_update_last_visit($forum_id);
                break;
            }
        }        
        /**
         * When a user visits a forum, save the time of that visit so we can compare later
         * @param type $user_id
         * @param type $forum_id
         * @return boolean 
         */
        
        function update_forums_visits_for_user($forum_id,$user_id=false){
            
            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if (get_post_type( $forum_id )!=bbp_get_forum_post_type()) return false;
            
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;
            
            $user_meta_key = $this->prefix.'_forums_visits';

            $visits = $this->get_forums_visits_for_user(false,$user_id);

            $visits[$forum_id] = current_time('timestamp');

            return update_user_meta( $user_id, $user_meta_key, $visits );


        }
        
        /**
         * Get the time of the last visit of the user for each forum.
         * At the first call, value is stored in $this->cuser_forums_visits for the current user,
         * so it's the "old" value is not erased when calling update_forums_visits_for_user.
         * @param type $forum_id
         * @param type $user_id
         * @return boolean 
         */
        function get_forums_visits_for_user($forum_id=false,$user_id=false){
            
            //validate forum
            if($forum_id)
                $forum_id = bbp_get_forum_id($forum_id);
            
            
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;
            
            $user_meta_key = $this->prefix.'_forums_visits';
            
            //if (($user_id==get_current_user_id())&&($this->cuser_forums_visits)) { //use the value already stored

              //  $meta = $this->cuser_forums_visits;
                
            //}else{
                
                $meta = get_user_meta($user_id,$user_meta_key, true );
                
              //  if ($user_id==get_current_user_id()){
                //    echo "store";
                    //$this->cuser_forums_visits = $meta;
                //}
            //}
            
            if($forum_id){
                return $meta[$forum_id];
            }else{
                return $meta;
            }
            
            

            
        }
        
        function get_user_last_forum_visit($forum_id,$user_id=false){
            return $this->get_forums_visits_for_user($forum_id,$user_id);
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
            
            //validate topic
            $topic_id = bbp_get_topic_id($topic_id);
            if (get_post_type( $topic_id )!=bbp_get_topic_post_type()) return false;
            
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

            $meta_key_name = $this->prefix.'_read_by';
            
            //if the key was never set, considerate as read
            if (!metadata_exists('post',$topic_id,$meta_key_name)) return true;
            
            $user_ids = get_post_meta($topic_id,$meta_key_name,true);

            return in_array($user_id,(array)$user_ids);
        } 

        
        function has_user_read_forum($forum_id,$user_id=false){
            
            //validate forum
            $forum_id = bbp_get_forum_id($forum_id);
            if (get_post_type( $forum_id )!=bbp_get_forum_post_type()) return false;
            
            //check user
            if(!$user_id) $user_id = get_current_user_id();
            if (!get_userdata( $user_id )) return false;

            //if the key was never set for the forums visits, considerate as read
            if (!metadata_exists('user',$user_id,$this->prefix.'_forums_visits')) return true;
            
            //if forum is empty, set to true
            $post_count = bbp_get_forum_post_count($forum_id);
            if(!$post_count) return true;
      
            $user_last_visit = $this->get_user_last_forum_visit($forum_id,$user_id);
            
            $forum_last_active_time = bbp_convert_date(get_post_meta( $forum_id, '_bbp_last_active_time', true ));

            return ($forum_last_active_time <= $user_last_visit);
        }

        function classes_attr($classes=false){
            if (!$classes) return false;
            return ' class="'.implode(" ",(array)$classes).'"';
            
        }
        
        function forum_mark_read_link(){
                global $wp,$post;
                
                $action_url = home_url( $wp->request );
                $action_name = 'mark_forum_read';
                $action_url = add_query_arg($this->action_varname,$action_name);
                $action_url = wp_nonce_url( $action_url, $action_name );
                
                $text = __('Mark all forum topics as read',$this->prefix);
                
                $classes[]=$this->prefix;
                $classes[]='action-link';
                $classes[]=$action_name;
                
                $classes_str = $this->classes_attr($classes);

                
                ?>
                <a<?php echo $classes_str;?> title="<?php echo $text;?>" href="<?php echo $action_url;?>"><?php echo $text;?></a>
                <?php
        }
        
}


/**
 * The main function responsible for returning the one true bbPress Unread Posts Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $pencil_bbp_unread = pencil_bbp_unread(); ?>
 *
 * @return The one true bbPress Unread Posts Instance
 */

function bbp_pencil_unread() {
	return bbP_Pencil_Unread::instance();
}

bbp_pencil_unread();

?>