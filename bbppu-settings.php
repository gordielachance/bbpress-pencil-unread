<?php
class bbP_Pencil_Unread_Settings {
    
    static $menu_slug = 'bbppu';
    
    var $menu_page;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );

        
	}

    function create_admin_menu(){

        $menu_page = add_options_page( 
            __( 'bbPress Pencil Unread', 'bbppu' ), //page title - I never understood why this parameter is needed for.  Put what you like ?
            __( 'bbPress Pencil Unread', 'bbppu' ), //menu title
            'manage_options', //cappability
            self::$menu_slug,
            array($this,'settings_page') //this function will output the content of the 'Music' page.
        );
        
        

    }

    function settings_sanitize( $input ){
        $new_input = array();

        if( isset( $input['reset_options'] ) ){
            
            $new_input = bbppu()->options_default;
            
        }else{ //sanitize values

            //test registration time
            $new_input['forums_marks'] = ( isset($input['forums_marks']) ) ? 'on' : 'off';
            $new_input['test_registration_time'] = ( isset($input['test_registration_time']) ) ? 'on' : 'off';
            $new_input['bookmarks'] = ( isset($input['bookmarks']) ) ? 'on' : 'off';
    
        }
        
        //remove default values
        foreach((array)$input as $slug => $value){
            $default = bbppu()->get_default_option($slug);
            if ($value == $default) unset ($input[$slug]);
        }

        //$new_input = array_filter($new_input); //disabled here because this will remove '0' values

        return $new_input;
        
        
    }

    function settings_init(){

        register_setting(
            'bbppu_option_group', // Option group
            bbppu()->options_metaname, // Option name
            array( $this, 'settings_sanitize' ) // Sanitize
         );
        
        add_settings_section(
            'settings_general', // ID
            __('General','bbppu'), // Title
            array( $this, 'bbppu_settings_general_desc' ), // Callback
            'bbppu-settings-page' // Page
        );

        add_settings_field(
            'marks', 
            __('Enable forums marks','bbppu'), 
            array( $this, 'enable_forums_marks_callback' ), 
            'bbppu-settings-page', // Page
            'settings_general'//section
        );

        add_settings_field(
            'test_registration_time', 
            __('Registration date check','bbppu'), 
            array( $this, 'test_registration_time_callback' ), 
            'bbppu-settings-page', // Page
            'settings_general'//section
        );
   
        add_settings_field(
            'bookmarks', 
            __('Enable bookmarks','bbppu'), 
            array( $this, 'enable_bookmark_callback' ), 
            'bbppu-settings-page', // Page
            'settings_general'//section
        );
        

        
        add_settings_section(
            'settings_system', // ID
            __('System','bbppu'), // Title
            array( $this, 'bbppu_settings_system_desc' ), // Callback
            'bbppu-settings-page' // Page
        );

        add_settings_field(
            'reset_options', 
            __('Reset Options','bbppu'), 
            array( $this, 'reset_options_callback' ), 
            'bbppu-settings-page', // Page
            'settings_system'//section
        );

    }
    
    function bbppu_settings_general_desc(){
        
    }
    
    function enable_forums_marks_callback(){
        $option = bbppu()->get_options('forums_marks');
        
        printf(
            '<input type="checkbox" name="%s[forums_marks]" value="on" %s /> %s',
            bbppu()->options_metaname,
            checked( $option, 'on', false ),
            __("Display a 'Mark as read' link in forums that marks all their topics","bbppu")
        );
    }
    
    function test_registration_time_callback(){
        $option = bbppu()->get_options('test_registration_time');
        
        printf(
            '<input type="checkbox" name="%s[test_registration_time]" value="on" %s /> %s',
            bbppu()->options_metaname,
            checked( $option, 'on', false ),
            __("Items older than the registration date of the user should be marked as read.","bbppu")
        );
    }
    
    function enable_bookmark_callback(){
        $option = bbppu()->get_options('bookmarks');
        
        printf(
            '<input type="checkbox" name="%s[bookmarks]" value="on" %s /> %s',
            bbppu()->options_metaname,
            checked( $option, 'on', false ),
            sprintf(__("Add bookmarks links %s after the topic title; to jump to the last read reply","bbppu"),'<code><span class="dashicons dashicons-flag"></span></code>')
        );
    }

    function bbppu_settings_system_desc(){
        
    }
    
    function reset_options_callback(){
        printf(
            '<input type="checkbox" name="%1$s[reset_options]" value="on"/> %2$s',
            bbppu()->options_metaname, // Option name
            __("Reset options to their default values.","bbppu")
        );
    }


	function  settings_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('bbPress Pencil Unread Settings','bbppu');?></h2>  
            
            <div>
                <?php
                $rate_link_wp = 'https://wordpress.org/support/view/plugin-reviews/bbpress-pencil-unread?rate#postform';
                $rate_link = '<a href="'.$rate_link_wp.'" target="_blank" href=""><i class="fa fa-star"></i> '.__('Reviewing it','bbppu').'</a>';
                $donate_link = '<a href="http://bit.ly/gbreant" target="_blank" href=""><i class="fa fa-usd"></i> '.__('make a donation','bbppu').'</a>';

                echo '<p><em>'.sprintf(__('Great experience with this plugin ? %s and %s would help us maintaining it !','bbppu'),$rate_link,$donate_link).'</em></p>';
                ?>
            </div>
            <hr/>
            <?php settings_errors('bbppu_option_group');?>
            <form method="post" action="options.php">
                <?php

                // This prints out all hidden setting fields
                settings_fields( 'bbppu_option_group' );   
                do_settings_sections( 'bbppu-settings-page' );
                submit_button();

                ?>
            </form>

        </div>
        <?php
	}
}

new bbP_Pencil_Unread_Settings;