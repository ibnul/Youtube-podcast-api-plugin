<?php



if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class My_Example_List_Table extends WP_List_Table {

    function __construct(){
        global $status, $page, $table_name, $wpdb;
        $table_name = $wpdb->prefix . 'youtube_data';

        parent::__construct( array(
            'singular'  => __( 'Channel', 'youtube-list' ),     //singular name of the listed records
            'plural'    => __( 'Channels', 'youtube-list' ),   //plural name of the listed records
            'ajax'      => false        //does this table support ajax?

        ) );

        add_action( 'admin_head', array( &$this, 'admin_header' ) );            

    }

    function admin_header() {
        $page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
        if( 'youtube-channel-list' != $page )
            return;
        echo '<style type="text/css">'; 
        ?>
        #cb.column-cb { width: 2%; }
        .column-channelimage { width: 9%; }
        .column-youtuber { width: 20%; }
        .column-totalvideos { width: 15%;}
        .column-subscribers { width: 10%;}
        .column-totalviews { width: 10%;}
        .column-updateTime { width: 15%;}
        .column-addedOn { width: 15%;}

        .channel-img{ width: 80%; border-radius: 50%; }
        .wp-list-table td { vertical-align: middle; }
        <?php
        echo '</style>';
    }

    function column_default( $item, $column_name ) {
        switch( $column_name ) { 
            case 'channelimage':
            case 'youtuber':
            case 'totalvideos':
            case 'subscribers':
            case 'totalviews':
            case 'updateTime':
            case 'addedOn':
            return $item[ $column_name ];
            default:
            return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'subscribers'  => array('subscribers',false),
            'totalviews' => array('totalviews',false),
            'updateTime'   => array('updateTime',false),
            'addedOn'   => array('addedOn',false)
        );
        return $sortable_columns;
    }

    function get_columns(){
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'channelimage'  => __( 'Channel Image', 'youtube-list' ),
            'youtuber'      => __( 'YouTuber', 'youtube-list' ),
            'totalvideos'   => __( 'Total Videos', 'youtube-list' ),
            'subscribers'   => __( 'Subscribers', 'youtube-list' ),
            'totalviews'    => __( 'Total Views', 'youtube-list' ),
            'updateTime'    => __( 'Last Update', 'youtube-list' ),
            'addedOn'       => __( 'Added On', 'youtube-list' ),
        );
        return $columns;
    }

    function column_channelimage($item){

        $actions = array(
            'delete' => sprintf('<a href="?page=%s&action=%s&channel=%s">Delete</a>',$_REQUEST['page'],'delete',$item['ID']),
            'update' => sprintf('<a href="?page=%s&action=%s&channel=%s">Update</a>',$_REQUEST['page'],'update',$item['ID']),
        );

        return sprintf('%1$s %2$s', $item['channelimage'], $this->row_actions($actions) );
    }

    function get_bulk_actions() {
        $actions = array(
            'bulk-delete'    => 'Delete',
            'bulk-update' => 'Update'
        );
        return $actions;
    }

    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="channel[]" value="%s" />', $item['ID']
        );    
    }

    public function process_bulk_action() {

        $screen = get_current_screen();

        // security check!
        if ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['_wpnonce'] ) ) {

            $nonce  = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
            $action = 'bulk-' . $this->_args['plural'];

            if ( ! wp_verify_nonce( $nonce, $action ) )
                wp_die( 'Nope! Security check failed!' );

        }

        $action = $this->current_action();

        switch ( $action ) {

            case 'delete':
            case 'bulk-delete':
                //wp_die( 'Delete something' );
                $channelid = $_GET['channel'];

                $this->delete_selected_channel($channelid);
                break;

            case 'update':
            case 'bulk-update':
                $channelid = $_GET['channel'];
                $this->update_selected_channel($channelid);
                break;

            default:
                // do nothing or something else
                return;
                break;
        }

        return;
    }

    function delete_selected_channel($ids){
        global $wpdb, $table_name;
        //$table_name = $wpdb->prefix . 'youtube_data';
 
        if( is_array($ids) ) {
            foreach($ids as $id){
                $query = "DELETE FROM `$table_name` WHERE id=$id";
                $deleted[] = $wpdb->query($query);
            } 
        }
        else{
            $query = "DELETE FROM `$table_name` WHERE id=$ids";
            $deleted = $wpdb->query($query);
        }
              
        

        //$redirect_url = add_query_arg('delete', $id, $redirect_url);
        if($deleted)
            printf('<div id="message" class="updated notice is-dismissable"><p>' . __('Selected channel has been deleted.', 'txtdomain') . '</p></div>');
              
    }
    function update_selected_channel($ids){ 

        if( is_array($ids) ) {
            foreach($ids as $id){
                $this->update_single_channel($id);
            }
        }
        else{
            $this->update_single_channel($ids);
        }
    }

    function update_single_channel($id){
        global $wpdb, $table_name;

        $options = get_option( 'wsbd_youtube_channel_data_settings' );
        $youtube_apikey = trim($options['apikey']);

        $select_query = "SELECT `channelID` FROM `$table_name` WHERE `id` = $id";
        $channelID = $wpdb->get_var($select_query);


        $video_subscribe_view = $this->wsbd_youtube_channel_count($channelID, $youtube_apikey);

        $channel_icon = $video_subscribe_view['channel_icon'];
        $channelTitle = $video_subscribe_view['channelTitle'];
        $videoCount = $video_subscribe_view['videoCount'];
        $subscriberCount = $video_subscribe_view['subscriberCount'];
        $viewCount = $video_subscribe_view['viewCount'];

        $latestVideoID = $this->wsbd_youtube_channel_latest_video($channelID, $youtube_apikey);
        //https://i.ytimg.com/vi/E4v2jeulyD8/default.jpg
        $popularVideoID = $this->wsbd_youtube_channel_popular_video($channelID, $youtube_apikey);
        //https://i.ytimg.com/vi/Z1LctxzEREE/default.jpg

        if( $channel_icon != NULL && $channelTitle != NULL){
            $this->wsbd_update_channel_data($channelID, $channel_icon, $channelTitle, $videoCount, $latestVideoID, $popularVideoID, $subscriberCount, $viewCount );
            //wsbd_ypcd_update_log( $channelID['channelID'] ." = updated");
            printf('<div id="message" class="updated notice is-dismissable"><p>' . __('Selected channel has been updated.', 'txtdomain') . '</p></div>');
        }
        else {
            //wsbd_ypcd_update_log( $channelID['channelID'] ." = FAILED");
            printf('<div id="message" class="updated notice is-dismissable"><p>' . __('There is something wrong with the query.', 'txtdomain') . '</p></div>');
        }
    }

/********************************** Start channel Count *****************/
    function wsbd_youtube_channel_count($channelID, $apikey){

        $json_link = 'https://www.googleapis.com/youtube/v3/channels?part=snippet%2Cstatistics&id=' . $channelID . '&key=' . $apikey;
 //var_dump($json_link);     
        $json_string = @file_get_contents( $json_link );

        if ($json_string !== false) 
            $json = json_decode($json_string, true);
     
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

        $json_string = @file_get_contents('https://www.googleapis.com/youtube/v3/search?part=snippet&channelId='. $channelID .'&maxResults=1&order=date&key=' . $apikey );


        if ($json_string !== false) $json = json_decode($json_string, true);

        if ( (is_array($json)) && (count($json) != 0) ) {
            $latestVideoID = $json['items']['0']['id']['videoId'];
        
        }
    
        return $latestVideoID;
        
    }

    /********************************** End Latest Video *****************/


    /********************************** Start Popular Video *****************/
    function wsbd_youtube_channel_popular_video($channelID, $apikey) {

        $json_string = @file_get_contents('https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=' . $channelID . '&maxResults=1&order=viewCount&key='. $apikey);

        if ($json_string !== false) $json = json_decode($json_string, true);

        if ( (is_array($json)) && (count($json) != 0) ) {
            $popularVideoID = $json['items']['0']['id']['videoId'];
        
        }

        return $popularVideoID;
    }
    /********************************** End Popular Video *****************/

    function wsbd_update_channel_data($channelID, $channel_icon, $channelTitle, $videoCount, $latestVideoID, $popularVideoID, $subscriberCount, $viewCount ){
        global $wpdb, $table_name;
        //$table_name = $wpdb->prefix . 'youtube_data';
        $date = date('Y-m-d H:i:s');

        if( !empty($channelID) ){           
            $update_query = $wpdb->query( 
                $wpdb->prepare( 
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
                ", $channel_icon, $channelTitle, $videoCount, $latestVideoID, $popularVideoID, $subscriberCount, $viewCount, $date, $channelID
                )
            ); 
        }
    } 


    function prepare_items() {

        global $wpdb, $_wp_column_headers, $table_name;
        $screen = get_current_screen();
        //$table_name = $wpdb->prefix . 'youtube_data';

        $search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : false;

        /* -- Preparing your query -- */
        $query = "SELECT * FROM `$table_name`";

        if(!empty($search)){
            $query .= " WHERE youtuber LIKE '%{$search}%'";
        }

        /* -- Ordering parameters -- */
        //Parameters that are going to be used to order the result
        $orderby = !empty($_GET["orderby"]) ? ($_GET["orderby"]) : 'subscribers';
        
        $order = !empty($_GET["order"]) ? ($_GET["order"]) : 'DESC';
        if(!empty($orderby) & !empty($order)){ 
            $query.=' ORDER BY '.$orderby.' '.$order; 
        }

/* -- Pagination parameters -- */
        //Number of elements in your table?
        $totalitems = $wpdb->query($query); //return the total number of affected rows

        //How many to display per page?
        $per_page = $this->get_screen_option_per_page();
        
        //Which page is this?
        //$paged = !empty($_GET["paged"]) ? $this->_real_escape($_GET["paged"]) : ’;
        $paged = $this->get_pagenum();
        //Page Number
        if(empty($paged) || !is_numeric($paged) || $paged<=0 ){ $paged=1; } 
        //How many pages do we have in total? 
        $totalpages = ceil($totalitems/$per_page); 
        //adjust the query to take pagination into account 
        if(!empty($paged) && !empty($per_page)){ 
            $offset=($paged-1)*$per_page; 
            $query.=' LIMIT '.(int)$offset.','.(int)$per_page; 
        } 
        /* -- Register the pagination -- */ 
        $this->set_pagination_args( array(
         "total_items" => $totalitems,
         "total_pages" => $totalpages,
         "per_page" => $per_page,
        ) );
      //The pagination links are automatically built according to those parameters

        /* -- Register the Columns -- */
        $columns = $this->get_columns();
        $_wp_column_headers[$screen->id] = $columns;
        //call bulk action function
        $this->process_bulk_action();

        /* -- Fetch the items -- */
        //$this->items = $wpdb->get_results($query);
//var_dump($query);
        $db_datas = $wpdb->get_results($query);
        $items_array = array();
        $single_item = array();

        if ( !empty($db_datas) ) {
            foreach( $db_datas as $db_data ){
                $single_item = array(
                    'ID' => $db_data->id,
                    'channelimage' => '<img class="channel-img" src="'.$db_data->channelimage.'"/>', 
                    'youtuber' => $db_data->youtuber, 
                    'totalvideos' => $db_data->totalvideos, 
                    'subscribers' => $db_data->subscribers, 
                    'totalviews' => $db_data->totalviews, 
                    'updateTime' => $db_data->updateTime,
                    'addedOn' => $db_data->addedOn,
                );
                array_push($items_array, $single_item);
            }
        }

        $this->items = $items_array;

    }


    /**
     * Get the screen option per_page.
     * @return int
     */
    function get_screen_option_per_page() {

         // get the current user ID
        $user = get_current_user_id();
        // get the current admin screen
        $screen = get_current_screen();

        // retrieve the "per_page" option
        $screen_option = $screen->get_option('per_page', 'option');
        //var_dump($screen_option);
        // retrieve the value of the option stored for the current user
        $per_page = get_user_meta($user, $screen_option, true);
        if ( empty ( $per_page) || $per_page < 1 ) {
            // get the default value if none is set
            $per_page = $screen->get_option( 'per_page', 'default' );
        }

        return $per_page;
    }


    function no_items() {
        _e( 'No Channel found, sorry.' );
    }


} //class


/*
function wps_add_menu_items(){
  $hook = add_menu_page( 'YouTube Channel List', 'YouTube Channel List', 'activate_plugins', 'youtube-channel-list', 'wps_render_list_page', 'dashicons-youtube' );
  add_action( "load-$hook", 'add_options' );
}
add_action( 'admin_menu', 'wps_add_menu_items' );*/

function add_options() {
  global $myListTable;
  $option = 'per_page';
  $args = array(
    'label' => 'Channels Per Page',
    'default' => 20,
    'option' => 'channels_per_page'
  );  
  add_screen_option( $option, $args );
  $myListTable = new My_Example_List_Table();
}


function wps_set_screen_option($status, $option, $value) {

    if ( 'channels_per_page' == $option ) 
        return $value;
    return $status;
    
}
add_filter('set-screen-option', 'wps_set_screen_option', 10, 3);



function wps_render_list_page(){
  global $myListTable;
  echo '<div class="wrap"><h2>YouTube Channel List</h2>'; 
  $myListTable->prepare_items();
  ?>
  <form method="GET">
    <input type="hidden" name="page" value="youtube-channel-list">
    <?php
    $myListTable->search_box( 'search', 'search_id' );

    $myListTable->display(); 
  echo '</form></div>'; 
}
