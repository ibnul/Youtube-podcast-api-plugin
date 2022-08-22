<?php 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/********************************** Start channel ID *****************/
function wsbd_getChannelID( $userName, $apikey ){
    $channelurl = "https://www.googleapis.com/youtube/v3/channels?key=". $apikey ."&forUsername=". $userName ."&part=id";

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $channelurl );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    $channelID = json_decode( curl_exec( $ch ) );

    //var_dump($channelID->items[0]->id);
    $channelID = $channelID->items[0]->id;

    return $channelID;
}

/********************************** End channel ID *****************/


// get itunes id from url
function wsbd_getPodcastID($itunes_url){
    preg_match('/id(\d+)/', $itunes_url, $podcast_id);

    return $podcast_id[1];
}

/********************************** End channel title *****************/


/********************************** Start channel Count *****************/
function wsbd_youtube_channel_count($channelID, $apikey){

    $json_string = @file_get_contents('https://www.googleapis.com/youtube/v3/channels?part=snippet%2Cstatistics&id=' . $channelID['channelID'] . '&key=' . $apikey );
   if ($json_string !== false) $json = json_decode($json_string, true);
  
   if ( (is_array($json)) && (count($json) != 0) ) {

        $temp = array();
        $temp['channel_icon'] = $json['items']['0']['snippet']['thumbnails']['medium']['url'];
        $temp['channelTitle'] = $json['items']['0']['snippet']['title'];
        $temp['viewCount']  = $json['items']['0']['statistics']['viewCount'];
        $temp['subscriberCount'] = $json['items']['0']['statistics']['subscriberCount'];
        $temp['videoCount'] = $json['items']['0']['statistics']['videoCount'];

        return $temp;

    }else {
        return 'Unavailable';
    }
}
/********************************** End channel count *****************/

/********************************** Start Latest Video ****************/
function wsbd_youtube_channel_latest_video($channelID, $apikey) {
    
    // Replace UC in channel id with UU to get playlist ID
    //$playlistID = substr_replace($channelID,'UU',0,2);

    //$json_string = @file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=' . $playlistID["channelID"] . '&maxResults=1&key=' . $apikey );
    $json_string = @file_get_contents('https://www.googleapis.com/youtube/v3/search?part=snippet&channelId='. $channelID["channelID"] .'&maxResults=1&order=date&key=' . $apikey );


    if ($json_string !== false) $json = json_decode($json_string, true);
 //var_dump('json'.$json);
    if ( (is_array($json)) && (count($json) != 0) ) {
        $latestVideoID = $json['items']['0']['id']['videoId'];
    
    }
    
    return $latestVideoID;
    
}

/********************************** End Latest Video *****************/


/********************************** Start Popular Video *****************/
function wsbd_youtube_channel_popular_video($channelID, $apikey) {
    //$url = "https://www.googleapis.com/youtube/v3/search?channelId=" . $channelID['channelID'] . "&key=" . $apikey ."&part=snippet,id&maxResults=2&sort=-views";
    $json_string = @file_get_contents('https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=' . $channelID["channelID"] . '&maxResults=1&order=viewCount&key='. $apikey);

    if ($json_string !== false) $json = json_decode($json_string, true);
 //var_dump('json'.$json);
    if ( (is_array($json)) && (count($json) != 0) ) {
        $popularVideoID = $json['items']['0']['id']['videoId'];
    
    }

    return $popularVideoID;
}
/********************************** End Popular Video *****************/

// function to update youtube data 
// dbTable name : wp_youtube_data
function wsbd_update_channel_data($channelID, $channel_icon, $channelTitle, $videoCount, $latestVideoID, $popularVideoID, $subscriberCount, $viewCount ){
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_data';
    $date = date('Y-m-d H:i:s');

    if( !empty($channelID) ){
            
        $update_query = $wpdb->query( $wpdb->prepare( 
            "UPDATE $table_name 
            SET channelimage = %s,
            youtuber = %s,
            totalvideos = %d,
            latestvideo = %s,
            popularvideo = %s,
            subscribers = %d,
            totalviews = %d,
            updateTime = %s
            WHERE channelID = %s                
            ", $channel_icon, $channelTitle, $videoCount, $latestVideoID, $popularVideoID, $subscriberCount, $viewCount, $date, $channelID['channelID']                
        ) ); 
    }
} 


// function lookup podcast data
function wsbd_lookup_podcast_data($podcast_id){
    $podcast = iTunes::lookup($podcast_id['podcast_id'], 'id', array(
        'entity' => 'podcast'
    ))->results;

//echo '<pre>' . var_export($result_array, true) . '</pre>';

    foreach($podcast as $item){
        $url = $item->feedUrl;
        $icon_url = $item->artworkUrl100;
        $trackViewUrl = $item->trackViewUrl;
        $podcasters_name = $item->collectionName;
        $recent_release_time = $item->releaseDate;
    }

    if($url != "") {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($curl);

        $xml = simplexml_load_string($data);

        $items = $xml->channel->item;
        
        $recent_title = (string) $items[0]->title;
        $recent_url = (string) $items[0]->enclosure['url'];
    }

    wsbd_update_podcast_data($podcast_id['podcast_id'], $trackViewUrl, $icon_url, $podcasters_name, $recent_title, $url, $recent_release_time );

}

// update podcast channel data function
function wsbd_update_podcast_data($podcast_id, $trackViewUrl, $icon_url, $podcasters_name, $recent_title, $recent_url, $recent_release_time ){
    global $wpdb;
    $table_name = $wpdb->prefix . 'podcast_data';
    $date = date('Y-m-d H:i:s');

    if( !empty($podcast_id) ){
            
        $update_query = $wpdb->query( $wpdb->prepare( 
            "UPDATE $table_name 
            SET itunes_url = %s,
            podcasters_logo = %s,
            podcasters_name = %s,
            recent_title = %s,
            recent_url = %s,
            recent_release_time = %s,
            updateTime = %s
            WHERE podcast_id = %d                
            ", $trackViewUrl, $icon_url, $podcasters_name, $recent_title, $recent_url, $recent_release_time, $date, $podcast_id
        ) ); 
    }
}

/*
   log the update status
*/ 
function wsbd_ypcd_update_log($msg){
    $date = date('Y-m-d H:i:s');
    #$log = $msg."   |  Date:  ".$date."\n";
    $log = " || Date: ". $date ." | ". $msg ." | \r\n";
    $pluginlog = plugin_dir_path(__FILE__).'wsbd-update.log';
    //$message = 'SOME ERROR'.PHP_EOL;  
    error_log($log, 3, $pluginlog);
} 

/* 
 * Log API call return data
 */

function mywps_ypcd_api_call_log($msg) {
	$date = date('Y-m-d H:i:s');
	$log = "= Date : ". $date." = ". $msg ."| \r\n";
	
	$pluginlog = plugin_dir_path(__FILE__).'mywps-api-call.log';
	
	error_log($log, 3, $pluginlog);
}
?>