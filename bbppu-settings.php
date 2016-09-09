<?php
class bbP_Pencil_Unread_Settings {
    
    static $menu_slug = 'bbppu';
    
    var $menu_page;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'current_screen', array( $this, 'review_rate_donate_notice') );

        
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
            $new_input['test_registration_time'] = ( isset($input['test_registration_time']) ) ? 'on' : 'off';
    
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
            'test_registration_time', 
            __('Registration date check','bbppu'), 
            array( $this, 'test_registration_time_callback' ), 
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
    
    function test_registration_time_callback(){
        $option = bbppu()->get_options('test_registration_time');
        
        printf(
            '<input type="checkbox" name="%s[test_registration_time]" value="on" %s /> %s',
            bbppu()->options_metaname,
            checked( $option, 'on', false ),
            __("Items older than the registration date of the user should be marked as read.","bbppu")
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
    
    //TO FIX TO CHECK, seems to be fired twice
    function review_rate_donate_notice(){
        
        if ( !bbppu()->is_bbppu_admin() ) return;
        
        $rate_link_wp = 'https://wordpress.org/support/view/plugin-reviews/bbpress-pencil-unread?rate#postform';
        $rate_link = '<a href="'.$rate_link_wp.'" target="_blank" href=""><i class="fa fa-star"></i> '.__('Reviewing it','bbppu').'</a>';
        $donate_link = '<a href="http://bit.ly/gbreant" target="_blank" href=""><i class="fa fa-usd"></i> '.__('make a donation','bbppu').'</a>';
        
        add_settings_error('bbppu_option_group', 'review_rate_donate', 
            sprintf(__('Happy with this plugin ? %s and %s would help!','bbppu'),$rate_link,$donate_link)
        );
    }

	function  settings_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('bbPress Pencil Unread Settings','bbppu');?></h2>  
            
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