<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// create custom plugin settings menu
add_action('admin_menu', 'wsbd_youtube_channel_menu');  
add_action( 'admin_init', 'wsbd_youtube_channel_settings_init' );

function wsbd_youtube_channel_menu() {

    //$hook = add_menu_page( 'YouTube Channel List', 'YouTube Channel List', 'activate_plugins', 'youtube-channel-list', 'wps_render_list_page', 'dashicons-youtube' );
    //add_action( "load-$hook", 'add_options' );

    //create new top-level menu
    $hook = add_menu_page('Youtube/Podcast', 'Youtube/Podcast', 'administrator', 'youtube-channel-list', 'wps_render_list_page' , 'dashicons-video-alt3' );
    add_action( "load-$hook", 'add_options' );

    add_submenu_page('youtube-channel-list', 'Youtube Channel List', 'Youtube Channel List', 'administrator', 'youtube-channel-list', 'wps_render_list_page');
    //add_submenu_page('youtube-channel-list', 'Podcast Channel List', 'Podcast Channel List', 'administrator', 'podcasters-list', 'wsbd_render_podcast_list');
    add_submenu_page('youtube-channel-list', 'Youtube Settings', 'Youtube  Settings', 'administrator', 'youtube-channel-settings', 'youtube_channel_settings_func' );
    add_submenu_page('youtube-channel-list', 'Add New YouTube', 'Add New YouTube', 'administrator', 'add-new-youtube-channel', 'add_new_channel_func' );
    add_submenu_page('youtube-channel-list', 'Add New Podcast', 'Add New Podcast', 'administrator', 'add-new-podcast-channel', 'add_new_podcast_func' );

    $hook2 = add_submenu_page('youtube-channel-list', 'Podcast Channel List', 'Podcast Channel List', 'administrator', 'podcasters-list', 'wsbd_render_podcast_list', 1 );
    add_action( "load-$hook2", 'podcast_add_options' );
}

function wsbd_youtube_channel_settings_init() {
    register_setting( 'youtubechannel', 'wsbd_youtube_channel_data_settings' );
    add_settings_section(
        'wsbd_youtube_channel_data_ytchannel_section',
        __( 'YouTube API Key', 'wordpress' ), 
        'wsbd_youtube_channel_data_settings_section_callback', 
        'youtubechannel'
    );
    add_settings_field( 
        'apikey', 
        __( 'API Key', 'wordpress' ), 
        'wsbd_apikey_render', 
        'youtubechannel', 
        'wsbd_youtube_channel_data_ytchannel_section' 
    );

}

function wsbd_apikey_render(  ) { 
    $options = get_option( 'wsbd_youtube_channel_data_settings' );
    //var_dump($options);
    ?>
    <input size='50' type='text' name='wsbd_youtube_channel_data_settings[apikey]' value='<?php echo $options['apikey']; ?>'>
    <?php
}


function wsbd_youtube_channel_data_settings_section_callback(  ) { 
    _e( 'You can register an API Key <a href="http://console.developers.google.com/project" target="_blank">here</a>.', 'wordpress' );
}

function youtube_channel_settings_func(){

 ?>
 <form action='options.php' method='post'>
    <h1>YouTube Channel Settings</h1>
    <?php //wsbd_podcast_api_call(); ?>
    <?php

    settings_fields( 'youtubechannel' );
    do_settings_sections( 'youtubechannel' );
    submit_button();

    ?>
</form>
<?php
}


function add_new_channel_func() {
    ?>
    <div class="wrap">
        <h1>Add Youtube Channel</h1>
        <style type="text/css">
.submit input {
    background: #0085ba;
    border-color: #0073aa #006799 #006799;
    box-shadow: 0 1px 0 #006799;
    color: #fff;
    text-decoration: none;
    text-shadow: 0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799;
    width: 111px;
    border-radius: 4px;
    padding: 8px;
    cursor: pointer;
}
        </style>
        <form method="post">
            <input type="hidden" name="action" value="add" />
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Youtube Channel ID</th>
                    <td><input type="text" name="new_youtube_channel_ID" value="" placeholder="UCtu-auoQOw5x6Ji0RcH_W5A" /></td>
                    <td>Please find the channel ID on the link like: <u>https://www.youtube.com/channel</u>/<b>UCl1JnSBowB7JWxmKiev6kSQ</b></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Youtube Channel User</th>
                    <td><input type="text" name="new_youtube_channel_name" value="" placeholder="wallstsurvivor" /></td>
                    <td>Please find the channel User on the link like: <u>https://www.youtube.com/user</u>/<b>ChristianPFdotcom</b></td>
                </tr>
                <tr><td class="submit"><input type="submit" value="Save" /></td></tr>

            </table>

        </form>
    </div>
<?php } 


if( isset($_POST['action']) ){
    if( $_POST['action'] == 'add' ) {
        //wsbd_youtube_channel_api_call();
        // Read posted values
        $channel_ID = $_POST['new_youtube_channel_ID'];
        $channel_name = $_POST['new_youtube_channel_name'];

        /* API Key */
        $options = get_option( 'wsbd_youtube_channel_data_settings' );
        $youtube_apikey = trim($options['apikey']);
        // Save the posted value
        if( !empty($channel_ID) || !empty($channel_name) ){
            if($channel_ID == ""){
                $channel_ID = wsbd_getChannelID($channel_name, $youtube_apikey);
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'youtube_data';

            $existing_id = $wpdb->get_results("
                SELECT channelID FROM `$table_name`
                WHERE channelID = '".$channel_ID."'");

            if($existing_id) {
                // Put an options updated message on the screen
                add_action('admin_notices', 'wsbd_add_new_channel_exists');                
            } 
            else {
                $result = wsbd_add_new_channel($channel_ID, $channel_name);
                // Put an options updated message on the screen
                if($result == true){
                    add_action('admin_notices', 'wsbd_add_new_channel_success');            
                }
                else{ 
                    add_action('admin_notices', 'wsbd_add_new_channel_failed');
                }
            }        
        }
    }

}

function wsbd_add_new_channel_success(){    ?>
    <div class="notice notice-success is-dismissible"><p><strong><?php _e('New Channel Added.', 'wsbd-yt-channel' ); ?></strong></p></div>
    <?php
}

function wsbd_add_new_channel_failed(){    ?>
    <div class="notice notice-error is-dismissible"><p><strong><?php _e('Error in New Channel Add. Please check fields.', 'wsbd-yt-channel' ); ?></strong></p></div> 
    <?php
}

// function if new channel exists
function wsbd_add_new_channel_exists(){ ?>
    <div class="notice notice-error is-dismissible"><p><strong><?php _e('This channel already added before.', 'wsbd-yt-channel' ); ?></strong></p></div> 
    <?php
}
// database queries
function wsbd_add_new_channel($channel_ID,$channel_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_data';
    $date = date('Y-m-d H:i:s');
    $query = "INSERT INTO `$table_name` (channelID, username, updateTime, addedOn) VALUES (%s, %s, %s, %s)";

    $result = $wpdb->query( $wpdb->prepare( $query, $channel_ID, $channel_name, $date, $date));

    return $result;
} 



// Add new podcast channel function
function add_new_podcast_func() { ?>
    <div class="wrap">
        <h1>Add Youtube Podcast</h1>
        <style type="text/css">
.submit input {
    background: #0085ba;
    border-color: #0073aa #006799 #006799;
    box-shadow: 0 1px 0 #006799;
    color: #fff;
    text-decoration: none;
    text-shadow: 0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799;
    width: 111px;
    border-radius: 4px;
    padding: 8px;
    cursor: pointer;
}
        </style>

        <form method="post">
            <input type="hidden" name="action" value="addPodcast" />
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">New iTunes URL</th>
                    <td colspan="2"><input style="width:500px" type="text" name="new_itunes_url" value="" placeholder="https://itunes.apple.com/us/podcast/.." /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">New Stitcher URL</th>
                    <td colspan="2"><input style="width:500px" type="text" name="new_stitcher_url" value="" placeholder="https://www.stitcher.com/podcast/stacking-benjamins" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">New Spotify URL</th>
                    <td colspan="2"><input style="width:500px" type="text" name="new_spotify_url" value="" placeholder="https://open.spotify.com/show/7fpFgeq4lOllFChMtFXG7B" /></td>
                </tr>
                <tr><td class="submit"><input type="submit" value="Save" /></td></tr>

            </table>

        </form>
    </div>
    <?php 
}


if( isset($_POST['action']) ){
    if( $_POST['action'] == 'addPodcast' ) {
        // Read posted values
        $itunes_url = $_POST['new_itunes_url'];
        $stitcher_url = $_POST['new_stitcher_url'];
        $spotify_url = $_POST['new_spotify_url'];

        // Save the posted value
        if( !empty($itunes_url) || !empty($stitcher_url) || !empty($spotify_url) ){

            $podcast_ID = wsbd_getPodcastID($itunes_url);

            global $wpdb;
            $table_name = $wpdb->prefix . 'podcast_data';

            $existing_id = $wpdb->get_results("
                SELECT podcast_id FROM `$table_name`
                WHERE podcast_id = '".$podcast_ID."'");

            if($existing_id) {
                // Put an options updated message on the screen
                add_action('admin_notices', 'wsbd_add_new_channel_exists');                
            }
            else {
                $result = wsbd_add_new_podcast($podcast_ID, $stitcher_url, $spotify_url);

                // Put an options updated message on the screen
                if($result == true){
                    add_action('admin_notices', 'wsbd_add_new_channel_success');
                }
                else{ 
                    add_action('admin_notices', 'wsbd_add_new_channel_failed');
                }
            }           
            
        }
    }
}


function wsbd_add_new_podcast($podcast_ID, $stitcher_url, $spotify_url){

    if ($podcast_ID == 0) {
        # something wrong in fields
        return false;
    }
    else{
        global $wpdb;
        $table_name = $wpdb->prefix . 'podcast_data';
        $date = date('Y-m-d H:i:s');
        $query = "INSERT INTO `$table_name` (podcast_id, stitcher_url, spotify_url, updateTime, addedOn) VALUES (%s, %s, %s, %s, %s)";

        $result = $wpdb->query( $wpdb->prepare( $query, $podcast_ID, $stitcher_url, $spotify_url, $date, $date));

        return $result;
    }
}
?>