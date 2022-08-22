<?php
/*
* Plugin Name: WPS YouTube Podcast API
* Version: 4.9.0
* Plugin URI: https://www.upwork.com/o/profiles/users/_~01c0d51a3194de2650/
* Description: This is a custom plugin developed for FinCon website. It fetches YOUTUBE & PODCAST data using API call once daily and updates the data into WP database custom tables.
* Author: Ibnul Hasan
* Author URI: https://www.upwork.com/o/profiles/users/_~01c0d51a3194de2650/
* Text Domain:       youtube-list
* Domain Path:       /languages
*/




defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

include_once(dirname(__FILE__) . '/inc/wsbd-functions.php');
include_once(dirname(__FILE__) . '/inc/wsbd-add-new-channel.php');
include_once(dirname(__FILE__) . '/inc/wsbd-api-call.php');
include_once(dirname(__FILE__) . '/inc/wsbd-itunes-api.php');
include_once(dirname(__FILE__) . '/inc/youtube-channel-list.php');
include_once(dirname(__FILE__) . '/inc/podcasters-list.php');

register_activation_hook(__FILE__, 'wsbd_yt_channel_activation');

function wsbd_yt_channel_activation() {
    if (! wp_next_scheduled ( 'wsbd_yt_api_call_event' )) {
        wp_schedule_event(time(), 'hourly', 'wsbd_yt_api_call_event');
    }
}

add_action('wsbd_yt_api_call_event', 'wsbd_call_yt_api_save');

function wsbd_call_yt_api_save() {
    // do something hourly
    wsbd_youtube_channel_api_call(); 
    wsbd_podcast_channel_api_call();   
}


 


register_deactivation_hook(__FILE__, 'wsbd_yt_channel_deactivation');

function wsbd_yt_channel_deactivation() {
    wp_clear_scheduled_hook('wsbd_yt_api_call_event');
}


/*
	Admin Options
*/

/********************* add Settings link ************************/
function wsbd_ytchannel_plugin_settngs_links( $links ) {
	$links = array_merge( array(
		'<a href="' . esc_url( admin_url( '/admin.php?page=youtube-channel-settings' ) ) . '">' . __( 'Settings', 'ytchannel' ) . '</a>'
	), $links );
	return $links;
}
    add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wsbd_ytchannel_plugin_settngs_links' );

/********************* /end add Settings link ************************/
?>