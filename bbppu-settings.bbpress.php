<?php

class bbP_Pencil_Unread_Settings {
    
    var $settings_slug = 'bbp_settings_bbppu';
    var $required_cap = 'bbp_settings_page';
    
    function __construct(){
        
        //http://www.hudsonatwell.co/tutorials/bbpress-development-add-settings/
        
        add_filter( 'plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 ); //settings link
        add_filter( 'bbp_admin_get_settings_sections', array( $this, 'add_bbppu_settings_section' ) ); //register settings section
        add_filter( 'bbp_admin_get_settings_fields', array( $this, 'add_bbppu_settings' ) ); //register settings fields
        add_filter('bbp_map_settings_meta_caps', array( $this, 'map_settings_capability') , 10, 4); //add capability for those settings
    }

	public function modify_plugin_action_links( $links, $file ) {

		// Return normal links if not bbPress
		if ( plugin_basename( $this->file ) !== $file ) {
			return $links;
		}

		// New links to merge into existing links
		$new_links = array();

		// Settings page link
		if ( current_user_can( $this->required_cap ) ) {
			$new_links['settings'] = '<a href="' . esc_url( add_query_arg( array( 'page' => 'bbpress'   ), admin_url( 'options-general.php' ) ) ) . '">' . esc_html__( 'Settings', 'bbpress' ) . '</a>';
		}

		// Add a few links to the existing links array
		return array_merge( $links, $new_links );
	}
    
    function map_settings_capability( $caps, $cap, $user_id, $args ){
        if ( $cap!=$this->settings_slug ) return $caps;
        
        $caps = array( bbpress()->admin->minimum_capability );
        return $caps;
    }
    
    function add_bbppu_settings_section($sections) {
        $new_sections = array(
            $this->settings_slug => array(
                'title'    => __( 'bbPress Pencil Unread', 'bbpress' ),
                'callback' => array(&$this,'bppu_settings_section_desc'),
                'page'     => 'discussion',
            )
        );
        
        return array_merge( $sections, $new_sections );
    }
    
    function bppu_settings_section_desc(){
        ?>
	       <p>
               <?php esc_html_e( 'Settings for bbPress Pencil Unread', 'bbppu' ); ?>
            </p>
        <?php
    }
    
    function add_bbppu_settings($settings){
        $new_settings = array(
            /** BuddyPress ********************************************************/

            $this->settings_slug => array(

                // Are group forums enabled?
                '_bbp_bbppu' => array(
                    'title'             => __( 'Enable Group Forums', 'bbpress' ),
                    'callback'          => array($this,'bbp_admin_setting_callback_group_forums'),
                    'sanitize_callback' => 'intval',
                    'args'              => array()
                ),

                // Group forums parent forum ID
                '_bbp_group_forums_root_id' => array(
                    'title'             => __( 'Group Forums Parent', 'bbpress' ),
                    'callback'          => 'bbp_admin_setting_callback_group_forums_root_id',
                    'sanitize_callback' => 'intval',
                    'args'              => array()
                )
            )
        );
            
        return array_merge( $settings, $new_settings );

    }
    
    /**
     * Allow BuddyPress group forums setting field
     *
     * @since bbPress (r3575)
     *
     * @uses checked() To display the checked attribute
     */
    function bbp_admin_setting_callback_group_forums() {
    ?>

        <input name="_bbp_enable_group_forums" id="_bbp_enable_group_forums" type="checkbox" value="1" <?php checked( bbp_is_group_forums_active( true ) );  bbp_maybe_admin_setting_disabled( '_bbp_enable_group_forums' ); ?> />
        <label for="_bbp_enable_group_forums"><?php esc_html_e( "Mark items created before user's registration as read", 'bbpress' ); ?></label>

    <?php
    }
    
}

new bbP_Pencil_Unread_Settings();