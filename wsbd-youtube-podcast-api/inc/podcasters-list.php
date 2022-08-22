<?php


if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Podcast_List_Table extends WP_List_Table {

    function __construct(){
        global $status, $page, $table_name, $wpdb;
        $table_name = $wpdb->prefix . 'podcast_data';

        parent::__construct( array(
            'singular'  => __( 'Podcast', 'youtube-list' ),     //singular name of the listed records
            'plural'    => __( 'Podcasts', 'youtube-list' ),   //plural name of the listed records
            'ajax'      => true        //does this table support ajax?

        ) );

        add_action( 'admin_head', array( &$this, 'admin_header' ) );            

    }

    function admin_header() {
        $page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
        if( 'podcasters-list' != $page )
            return;
        echo '<style type="text/css">'; 
        ?>
        .column-cb { width: 2%; }
        .column-podcasters_logo { width: 9%; }
        .column-podcasters_name { width: 20%; }
        .column-recent_title { width: 30%;}
        .column-recent_release_time { width: 13%;}
        .column-updateTime { width: 13%;}
        .column-addedOn { width: 13%;}


        .channel-img{ width: 90%; }
        .wp-list-table td { vertical-align: middle; }
        <?php
        echo '</style>';
    }

    function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'podcasters_logo':
            case 'podcasters_name':
            case 'recent_title':
            case 'recent_release_time':
            case 'updateTime':
            case 'addedOn':
                return $item[ $column_name ];

            default:
                return print_r( $item, true ) ;
        }
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'recent_release_time'  => array('recent_release_time',false),
            'updateTime'   => array('updateTime',false),
            'addedOn'   => array('addedOn',false)
        );
        return $sortable_columns;
    }

    function get_columns(){
        $columns = array(
            'cb'                    => '<input type="checkbox" />',
            'podcasters_logo'       => 'Logo',
            'podcasters_name'       => 'Podcaster',
            'recent_title'          => 'Recent Title',
            'recent_release_time'   => 'Release Time',
            'updateTime'            => 'Update Time',
            'addedOn'               => 'Added On',
        );

        return $columns;
    }

    function column_podcasters_logo($item){

        $actions = array(
            'delete' => sprintf('<a href="?page=%s&action=%s&channel=%s">Delete</a>',$_REQUEST['page'],'delete',$item['ID']),
            'update' => sprintf('<a href="?page=%s&action=%s&channel=%s">Update</a>',$_REQUEST['page'],'update',$item['ID']),
        );

        return sprintf('%1$s %2$s', $item['podcasters_logo'], $this->row_actions($actions) );
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
            printf('<div id="message" class="updated notice is-dismissable"><p>' . __('Selected Podcast has been deleted.', 'txtdomain') . '</p></div>');
              
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

        //$options = get_option( 'wsbd_youtube_channel_data_settings' );
        //$youtube_apikey = trim($options['apikey']);

        $select_query = "SELECT `podcast_id` FROM `$table_name` WHERE `id` = $id";
        $channelID = $wpdb->get_var($select_query);

        $this->wsbd_lookup_podcast_data($channelID);
       
    }

    // function lookup podcast data
    function wsbd_lookup_podcast_data($podcast_id){

        $podcast = iTunes::lookup($podcast_id, 'id', array(
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

        if($url != NULL) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($curl);

            $xml = simplexml_load_string($data);

            $items = $xml->channel->item;
            
            $recent_title = (string) $items[0]->title;
            $recent_url = (string) $items[0]->enclosure['url'];

            if( $icon_url != NULL && $podcasters_name != NULL && $recent_release_time != NULL){
                $this->wsbd_update_podcast_data($podcast_id, $trackViewUrl, $icon_url, $podcasters_name, $recent_title, $url, $recent_release_time );
                //wsbd_ypcd_update_log( $channelID['channelID'] ." = updated");
                printf('<div id="message" class="updated notice is-dismissable"><p>' . __('Selected channel has been updated.', 'txtdomain') . '</p></div>');
            }
            else {
                //wsbd_ypcd_update_log( $channelID['channelID'] ." = FAILED");
                printf('<div id="message" class="updated notice is-dismissable"><p>' . __('There is something wrong with the query.', 'txtdomain') . '</p></div>');
            }
        }
        else {
            //wsbd_ypcd_update_log( $channelID['channelID'] ." = FAILED");
            printf('<div id="message" class="updated notice is-dismissable"><p>' . __('Podcasters Channel Not Found!.', 'txtdomain') . '</p></div>');
        }



        

    }

    // update podcast channel data function
    function wsbd_update_podcast_data($podcast_id, $trackViewUrl, $icon_url, $podcasters_name, $recent_title, $recent_url, $recent_release_time ){
        global $wpdb, $table_name;
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





    function prepare_items() {

        global $wpdb, $_wp_column_headers, $table_name;
        $screen = get_current_screen();
        //$table_name = $wpdb->prefix . 'youtube_data';

        $search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : false;

        /* -- Preparing your query -- */
        $query = "SELECT * FROM `$table_name`";

        if(!empty($search)){
            $query .= " WHERE podcasters_name LIKE '%{$search}%'";
        }

        /* -- Ordering parameters -- */
        //Parameters that are going to be used to order the result
        $orderby = !empty($_GET["orderby"]) ? ($_GET["orderby"]) : 'recent_release_time';
        
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
                    'podcasters_logo' => '<img class="channel-img" src="'.$db_data->podcasters_logo.'"/>', 
                    'podcasters_name' => $db_data->podcasters_name, 
                    'recent_title' => $db_data->recent_title, 
                    'recent_release_time' => $db_data->recent_release_time, 
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

function podcast_add_options() {
  global $objPodcastListTable;
  $option = 'per_page';
  $args = array(
    'label' => 'Podcast Per Page',
    'default' => 20,
    'option' => 'podcast_per_page'
  );  
  add_screen_option( $option, $args );
  $objPodcastListTable = new Podcast_List_Table();
}


function podcast_set_screen_option($status, $option, $value) {

    if ( 'podcast_per_page' == $option ) 
        return $value;
    return $status;
    
}
add_filter('set-screen-option', 'podcast_set_screen_option', 10, 3);



function wsbd_render_podcast_list(){
  global $objPodcastListTable;
  echo '<div class="wrap"><h2>Podcasters Channel List</h2>'; 
  $objPodcastListTable->prepare_items();
  ?>
  <form method="GET">
    <input type="hidden" name="page" value="podcasters-list">
    <?php
    $objPodcastListTable->search_box( 'search', 'search_id' );

    $objPodcastListTable->display(); 
  echo '</form></div>'; 
}
