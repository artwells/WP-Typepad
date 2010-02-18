<?php
/*
Plugin Name: TypePad
Plugin URI: http://sixapart.com/wordpress 
Description: TypePad AntiSpam and Six Apart Media services for WordPress.
Version: 0.0.3b
Author: Art Wells, Six Apart, Matt Mullenweg
Author URI: http://sixapart.com/wordpress
*/

/*  Copyright 2009 Six Apart  (email : info@sixapart.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA


*/


/**
 * Constants
 * most development-oriented
 * search and replace once the code settles down
 */

/**
 * directory off of wp-content/plugins 
 * can be removed once things settle down
 */
define('TY_BUNDLE_DIR', 'typepad');
/**
 * name present in admin text
 * search and replace once the code settles down
 */
define('TY_NAME','TypePad');

/**
 * page used in ?page= URLs
 * search and replace once the code settles down

 */
define('TY_PAGE','typepad');
/**
 * menu to place the controls under 
 * can be removed once things settle down
 */
define('TY_SUPER_MENU','plugins.php');
/**
 * software's landing page
 * search and replace once the code settles down
 */
define('TY_HOME','http://sixapart.com/wordpress');
/**
 * software's landing page
 * search and replace once the code settles down
 */
define('TY_KEY_URL','http://antispam.typepad.com/info/get-api-key.html');


/**
 * what the Media looks for in posts to place ads
 */
define('TY_FILTER_PREFIX','TP_AD_');

/**
 * AntiSpam Constants
 * adapted from old plugin
 */
/**
 * Base hostname for API requests (API key is always prepended to this)
 */
define('TYPEPADANTISPAM_SERVICE_HOST','api.antispam.typepad.com');
/**
 * URL for the home page for the AntiSpam service
 */
define('TYPEPADANTISPAM_SERVICE_URL','http://antispam.typepad.com/');
/**
 * URL for the page where a user can obtain an API key
 */
define('TYPEPADANTISPAM_APIKEY_URL','http://antispam.typepad.com/');
/**
 * Plugin version
 */
define('TYPEPADANTISPAM_PLUGIN_VER','1.02');
/**
 * API Protocol version
 */
define('TYPEPADANTISPAM_PROTOCOL_VER','1.1');
/**
 * Port for API requests to service host
 */
define('TYPEPADANTISPAM_API_PORT',80);




//http://codex.wordpress.org/Determining_Plugin_and_Content_Directories
if ( ! defined( 'WP_CONTENT_URL' ) ) {
    define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ){
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
if ( ! defined( 'WP_PLUGIN_URL' ) ){
    define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ){
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}
/**
 * directory for the plug in
 */

define('TY_PLUGIN_DIR',WP_PLUGIN_URL . '/' .TY_BUNDLE_DIR);

////////////////////////////////////


/**
 * Contains the AntiSpam and Media functions
 *
 * @todo should break it into different classes
 * @todo run javascript triggers through a check of $_enabledMedia
 *  
 */
class typePadBundle {
    /**
     * trigger for whether or not typepad has been run before or purged 
     */
    private $_firstRun='';
    /**
     * admin's choice to run
     * won't trigger a disabling of the javascript inserted in theme, yet
     */
    private $_enabledMedia='';
    /**
     * admin's choice to run
     * to add hooks or not
     */
    private $_enabledAntiSpam='';
    /**
     * where admin choice took
     */
    private $_formSuccess=array();
    private $_formFailure=array();
    /**
     * holder of the value for typepad_antispam_key
     */
    private $_antispamKey='';
    /**
     * raw input from the form
     * this should be handled better/ not stored after its been derived into mediaCode
     */
    private $_leaderboardCode='';
    /**
     * ad number extracted from leaderboardCode
     * used to arithmetically derive other ad formats
     */
    private $_mediaCode='';
    /**
     * whether or not to throw out spam matches after a month
     */
    private $_antispamTrash='';
    /**
     * api-number specific host name for AntiSpam queries
     */
    private $_antiSpamHost='';
    /**
     * holder of GLOBALS['wpdb']
     */
    private $_wpdb='';
    /**
     * holder of $submenu in case it's set environmentally
     */
    private $_submenu='';

    /**
     * establishes most values from get_options
     * most of the add_actions
     */
    public function  __construct() {
        /* pulling from the environment */
        $this->_wpdb=$GLOBALS['wpdb'];
        $this->_submenu=$GLOBALS['submenu'];
        /* if typepad_media_enabled isn't in the database, it's safe to
         * say this is the first run or the database has been purged of 'typepad_' entries
         */
        if(get_option('typepad_media_enabled')===false){
            add_option('typepad_media_enabled', '1', 'Is Six Apart Media enabled?', 'yes');
            $this->_firstRun=true;
        }
        $this->_enabledMedia=get_option('typepad_media_enabled');
        $this->_mediaCode=get_option('typepad_media_code');
        /* if the media is enabled and there is no media code and the admin isn't working on it, alert admin */
        if($this->_enabledMedia && empty($this->_mediaCode) &&  strpos( $_SERVER['REQUEST_URI'],TY_SUPER_MENU . '?page=' . TY_PAGE)===false){
            add_action('admin_notices' ,array(&$this, 'noMediaCode'),1);  
        }

        $this->_enabledAntiSpam=get_option('typepad_antispam_enabled');
        $this->_antispamKey=get_option('typepad_antispam_key');

        if($this->_enabledAntiSpam){
             if(empty($this->_antispamKey)){
                 if(strpos($_SERVER['REQUEST_URI'],TY_SUPER_MENU . '?page=' . TY_PAGE )===false){
                     /* if the antispam is enabled and there is no key and the admin isn't working on it, alert admin */
                     add_action('admin_notices' ,array(&$this, 'noAntiSpamKey'),1);
                 }
             }
             else{
                /* if antispam is enabled and the key is there, add the hooks */
                $this->_antiSpamHost = $this->_antispamKey . '.' . TYPEPADANTISPAM_SERVICE_HOST;
                add_action('wp_set_comment_status', array(&$this, 'submitSpamComment'),1);
                add_action('edit_comment', array(&$this, 'antispamSubmitSpamComment'));
                add_action('preprocess_comment', array(&$this, 'antispamAutoCheckComment'),1);
                add_action('admin_menu', array(&$this, 'antispamManagePage'));
                add_action('rightnow_end', array(&$this, 'antispamRightnow'));
                add_action('manage_comments_nav', array(&$this, 'antispamCheckForSpamButton'));
                add_action('init', array(&$this, 'widgetAntispamRegister'));
             }
        }
        /* was conditional upon mediaEnabled, but we don't want to leave the codes in content */
        add_filter('the_content', array(&$this, 'contentMediaFilter'),-10, 1);
        /* we want these even if they aren't enabled, since the form might require it */
        $this->_leaderboardCode=get_option('typepad_leaderboard_code');
        $this->_antispamTrash=get_option('typepad_antispam_trash');
        /* action item for admin */
        if($this->_firstRun){
            add_action('admin_notices' ,array(&$this, 'typeWelcome'),1)  ;
        }
        /* add forms and their styles */
        add_action ('admin_menu', array(&$this, 'typeAdmin'),3);
        add_action("admin_head", array(&$this, "tyAdminCss"));

    }
    /**
     * simple Not Enough Info alert
     */
    public function noMediaCode() {
        echo '<div class="updated fade"><p><strong>Media Enabled Without Code.  Please see ';
        echo 'the <a href="' . TY_SUPER_MENU . '?page=' . TY_PAGE . '">\''.TY_NAME.'\' Plugin Page</a>.</strong></p></div>';
    }
    /**
     * simple Not Enough Info alert
     */
    public function noAntiSpamKey() {
        echo '<div class="updated fade"><p><strong>AntiSpam Enabled Without Key.  Please see ';
        echo 'the <a href="' . TY_SUPER_MENU . '?page=' . TY_PAGE . '">\''.TY_NAME.'\' Plugin Page</a>.</strong></p></div>';
    }

    
    /**
     * looks for special Media code in Content and replaces it with appropriate add
     */
    public function contentMediaFilter($content){
        if(strpos($content,TY_FILTER_PREFIX)!==false){
            $content=preg_replace('/'.TY_FILTER_PREFIX.'([0-9]+)_([A-Z]+)/e','$this->mediaChoice(\\1,\\2)',$content);
        }
        return $content;
    }


    /**
     * fired by contentMediaFilter above
     * I feel like the dims are redundant and seem like nasty
     * things to be including in a function
     * should be moved to setting/array at least
     */
    public function mediaChoice($id,$size){
        if(!$this->_enabledMedia){
            //removes codes in content if its diabled
            return null;
        }
        if($size=="LEADER"){
            return '<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id='.$id.'&width=728&height=90&js=1"></script>';
        }
        if($size=="SIDEBAR"){
            return '<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id='.$id.'&width=160&height=600&js=1"></script>';
        }
        if($size=="RECTANGLE"){
            return '<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id='.$id.'&width=300&height=250&js=1"></script>';
        }
        //must be badge?
        return '<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id='.$id.'&width=160&height=90&js=1"></script>';
    }
    /* just css colors, etc. */
    public function tyAdminCss(){
        echo '<link rel="stylesheet" href="'.TY_PLUGIN_DIR.'/typepad-admin.css" type="text/css" media="screen" />';
    }
    
    /**
     * triggered by firstRun
     * seeks out usable values for the AntiSpam key
     * adds essential optins and alerts admin
     */

    public function typeWelcome(){
        if(!$this->_antispamKey){ //could be a second run of firstRun for several reasons
            if($antipsam_key=get_option('typepadantispam_api_key')){ //for older installs
                add_option('typepad_antispam_key', $antipsam_key, 'TypePad API key', 'yes');
            }
            if($antipsam_key=get_option('wordpress_api_key')){ //reuse of key by request.  are we sure it's validate?
                add_option('typepad_antispam_key', $antipsam_key, 'TypePad API key', 'yes');
            }
            else{
                add_option('typepad_antispam_key', '' , 'TypePad API key', 'yes');
            }
            $this->_antispamKey=$antispam_key;
        }
        if(!$this->_leaderboardCode){
            add_option('typepad_leaderboard_code', '' , 'Raw TypePad Media Ad Code Used To Extract ID', 'yes');
        }
        if(!$this->_mediaCode){
            add_option('typepad_media_code', '' , 'Typepad Media Ad Code ID', 'yes');
        }
        add_option('typepad_antispam_enabled', '1', 'Is TypePad AntiSpam enabled?', 'yes');
        add_option('typepad_antispam_count','0','AntiSpam Spam Caught Count','yes');

        echo '<div class="updated fade"><p><strong>TypePad Services Installed.  Please see ';
        echo 'the <a href="' . TY_SUPER_MENU . '?page=' . TY_PAGE . '">\''.TY_NAME.'\' Plugin Page</a>.</strong></p></div>';
            
    }

    /**
     * creates management page
     * adds jquery for minor effects--could be used more for adding media, etc.
     */
    public function typeAdmin(){
        add_submenu_page(TY_SUPER_MENU, TY_NAME, TY_NAME, 'manage_options', TY_PAGE, array(&$this, 'typeDisplayConf'));
        wp_enqueue_script('jquery');
    }
    /**
     * gets the form
     */
    public function typeDisplayConf() {
        require(WP_PLUGIN_DIR . '/' . TY_BUNDLE_DIR . '/typepadConf.php');
    }

    /**
     * processes form data and does some data processing
     *
     * @param array $inputs all of the $_REQUEST that is from the form
     * @return array ready-to-print values 
     */

    private function _processConfigChange($inputs){
        
        $change_success=true;
        /* used by the old plugin's functions */
        $key_check='';
        $fail_note;
        /**
         * the below should be abstracted to a generic method
         * with a bit extra to handle additional functions in leaderboard code
         */
        if($inputs['media_enabled']!=''
           && $inputs['media_enabled'] != $this->_enabledMedia 
           ){
            $change_success=false;
            if(is_numeric($inputs['media_enabled'] )){

                if(update_option('typepad_media_enabled',$inputs['media_enabled'])){

                    $this->_enabledMedia=$inputs['media_enabled'];
                    $change_success=true;
                }
            }
            if( $change_success){
                $this->_formSuccess[]='Media Enabled';
            }
            else{
                $this->_formFailure[]='Media Enabled';
            }
        }
        if($inputs['antispam_enabled']!=''
           && $inputs['antispam_enabled'] != $this->_enabledAntiSpam
           ){
            $change_success=false;
            if(is_numeric($inputs['antispam_enabled'] )){
                if(update_option('typepad_antispam_enabled',$inputs['antispam_enabled'])){
                    $this->_enabledAntiSpam=$inputs['antispam_enabled'];
                    $change_success=true;
                }
            }
            if( $change_success){
                $this->_formSuccess[]='AntiSpam Enabled';
            }
            else{
                $this->_formFailure[]='AntiSpam Enabled';
            }
        }
        if($inputs['antispam_trash']!=''
           && $inputs['antispam_trash'] != $this->_antispamTrash
           ){
            $change_success=false;
            if(is_numeric($inputs['antispam_trash'] )){
                if(update_option('typepad_antispam_trash',$inputs['antispam_trash'])){
                    $this->_antispamTrash=$inputs['antispam_trash'];
                    $change_success=true;
                }
            }
            if( $change_success){
                $this->_formSuccess[]='AntiSpam Trash';
            }
            else{
                $this->_formFailure[]='AntiSpam Trash';
            }
        }
        if(!empty($inputs['leaderboard_code'])){
            if($inputs['leaderboard_code']!=$this->_leaderboardCode){
                $change_success=false;
                $cleaned['leaderboard_code']=$inputs['leaderboard_code'];//not much to do here, I guess, wordpress cleans as it disinfects
                if(preg_match('/\bid=([0-9]+)\b/',$cleaned['leaderboard_code'],$matches)){
                    if(update_option('typepad_leaderboard_code',$cleaned['leaderboard_code'])){
                        if(update_option('typepad_media_code',$matches[1])){
                            $change_success=true;
                            $this->_mediaCode= $cleaned['media_code']=$matches[1];
                            $this->_leaderboardCode=$cleaned['leaderboard_code'];
                        }
                    }
                    if( $change_success){
                        $this->_formSuccess[]='Media Code';
                    }
                    else{
                        $this->_formFailure[]='Media Code';
                    }
                }
                else{
                    $this->_formFailure[]='Media Code (bad format)';
                }
            }
        }
        elseif(!empty($this->_leaderboardCode)){
            $change_success=false;
            if(update_option('typepad_leaderboard_code','') && update_option('typepad_media_code','')){
                $change_success=true;
                unset($this->_leaderboardCode);
                unset($this->_mediaCode);
            }
            if( $change_success){
                $this->_formSuccess[]='Delete Media Code';
            }
            else{
                $this->_formFailure[]='Delete Media Code';
            }
        }

        //put in antispam key validators
        if(!empty($inputs['antispam_key'])){
            if($inputs['antispam_key']!=$this->_antispamKey){
                $change_success=false;
                $cleaned['antispam_key']=preg_replace('/[^a-z0-9]/','',$inputs['antispam_key']);
                $key_status= $this->_antispamVerifyKey( $cleaned['antispam_key'] );
                if($key_status=="valid"){
                    if(update_option('typepad_antispam_key',$cleaned['antispam_key'])){
                        $change_success=true;
                    }
                }
                else {
                    if ( empty( $this->_antispamKey ) ) {
                        if ( $key_status != 'failed' ) {
                            if ( $this->_antispamVerifyKey( '1234567890ab' ) == 'failed' ){
                                $fail_note[] = 'No Connection';
                            }
                            else{
                                $fail_note[] = 'Key Empty';
                            }
                        }
                        $key_status = 'empty';
                    } else {
                        $key_status = $this->_antispamVerifyKey( $this->_antispamKey  );
                    }
                    if ( $key_status == 'valid' ) {
                        $change_success=true;
                    } else if ( $key_status == 'invalid' ) {
                        delete_option('typepad_antispam_key');
                    } 
                }

                if( $change_success){
                    $this->_formSuccess[]='Valid Key';
                }
                else{
                    $this->_formFailure[]='Key Failed ('.implode(', ',$fail_note).')';
                    unset($fail_note);
                }
            }
            //otherwise nothing to do with this
        }
        elseif(!empty($this->_antispamKey)){
            $change_success=false;
            if(update_option('typepad_antispam_key','')){
                $change_success=true;
                unset($this->_antispamKey);
            }
            if( $change_success){
                $this->_formSuccess[]='Deleted Key';
            }
            else{
                $this->_formFailure[]='Deleted Key';
            }
        }
        return $cleaned;

    }
    /**
     * the below is the old plugin with functions mapped to camelCase
     * and globals swapped for the constants defined external to the class
     * documentation is pretty much as it was found
     * some code supporting legacy wordpress installs needs to be brought in
     */


    /**
     * incomplete from the original typepadantispam plugin 
     * should distinguish between failed etc.
     * but then, does the server actually ever return invalid?
     */
    private function _antispamVerifyKey( $key ) {
        $blog = urlencode( get_option('home') );
        $response = $this->_antispamHttpPost('key=' . $key . '&blog='. $blog, TYPEPADANTISPAM_SERVICE_HOST,
                                             '/' . TYPEPADANTISPAM_PROTOCOL_VER .'/verify-key', TYPEPADANTISPAM_API_PORT);
        if ( !is_array($response) || !isset($response[1]) || $response[1] != 'valid' && $response[1] != 'invalid' ){
            return 'failed';
        }
        return $response[1];
    }

    private function _antispamHttpPost($request, $host, $path, $port = 80) {
        $http_request  = "POST $path HTTP/1.0\r\n";
        $http_request .= 'Host: ' . $host . "\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
        $http_request .= "Content-Length: " . strlen($request) . "\r\n";
        $http_request .= 'User-Agent: WordPress/'.$GLOBALS['wp_version'].' | TypePadAntiSpam/';
        $http_request .=  TYPEPADANTISPAM_PLUGIN_VER . "\r\n";
        $http_request .= "\r\n";
        $http_request .= $request;
        $response = '';
        if( false != ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
            fwrite($fs, $http_request);

            while ( !feof($fs) ) {
                $response .= fgets($fs, 1160); // One TCP-IP packet
            }
            fclose($fs);
            $response = explode("\r\n\r\n", $response, 2);
        }
        return $response;
    }




    public function submitSpamComment($comment_id){
        $comment_id = (int) $comment_id;
        $comment = $this->_wpdb->get_row("SELECT * FROM ".$this->_wpdb->comments." WHERE comment_ID = '$comment_id'");
        if ( !$comment ) // it was deleted
            return;
        if ( 'spam' != $comment->comment_approved )
            return;
        $comment->blog = get_option('home');
        $post_id = (int) $comment->comment_post_ID;
        $post = get_post( $post_id );
        if ( !$post ) // deleted
            return;
        $comment->article_date = preg_replace('/\D/', '', $post->post_date);
        $query_string = '';
        foreach ( $comment as $key => $data ){
            $query_string .= $key . '=' . urlencode( stripslashes($data) ) . '&';
        }
        $response = $this->_antispamHttpPost($query_string, $this->_antiSpamHost, '/'.TYPEPADANTISPAM_PROTOCOL_VER.'/submit-spam', TYPEPADANTISPAM_API_PORT);
    }


    // Total spam in queue
    // get_option( 'typepad_antispam_count' ) is the total caught ever
    function _antispamCount( $type = false ) {
        if ( !$type ) { // total
            $count = wp_cache_get( 'typepad_antispam_count', 'widget' );
            if ( false === $count ) {
                if ( function_exists('wp_count_comments') ) {
                    $count = wp_count_comments();
                    $count = $count->spam;
                } else {
                    $count = (int) $this->_wpdb->get_var("SELECT COUNT(comment_ID) FROM ".$this->_wpdb->comments." WHERE comment_approved = 'spam'");
                }
                wp_cache_set( 'typepad_antispam_count', $count, 'widget', 3600 );
            }
            return $count;
        } elseif ( 'comments' == $type || 'comment' == $type ) { // comments
            $type = '';
        } else { // pingback, trackback, ...
            $type  = $this->_wpdb->escape( $type );
        }

        return (int) $this->_wpdb->get_var("SELECT COUNT(comment_ID) FROM ".$this->_wpdb->comments." WHERE comment_approved = 'spam' AND comment_type='$type'");
    }




    private function _antispamCheckDbComment( $id ) {
        $id = (int) $id;
        $c = $this->_wpdb->get_row( "SELECT * FROM ".$this->_wpdb->comments." WHERE comment_ID = '".$id."'", ARRAY_A );
        if ( !$c ){
            return;
        }
        var_dump($c);
        $c['user_ip']    = $c['comment_author_IP'];
        $c['user_agent'] = $c['comment_agent'];
        $c['referrer']   = '';
        $c['blog']       = get_option('home');
        $post_id = (int) $c['comment_post_ID'];
        $post = get_post( $post_id );
        $c['article_date'] = preg_replace('/\D/', '', $post->post_date);
        $query_string = '';
        foreach ( $c as $key => $data ){
            $query_string .= $key . '=' . urlencode( stripslashes($data) ) . '&';
        }
        $response = $this->_antispamHttpPost($query_string, $this->_antiSpamHost, '/'.TYPEPADANTISPAM_PROTOCOL_VER.'/comment-check', TYPEPADANTISPAM_API_PORT);
        return $response[1];
    }


    // Counter for non-widget users
    private function _antispamCounter() {
        echo '
<style type="text/css">
#typepadantispamwrap #tpaa,#tpaa:link,#tpaa:hover,#tpaa:visited,#tpaa:active{text-decoration:none}
#tpaa:hover{border:none;text-decoration:none}
#tpaa:hover #typepadantispam1{display:none}
#tpaa:hover #typepadantispam2,#typepadantispam1{display:block}
#typepadantispam1{padding-top:5px;}
#typepadantispam2{display:none;padding-top:0px;color:#333;}
#typepadantispama{font-size:16px;font-weight:bold;line-height:18px;text-decoration:none;}
#typepadantispamcount{display:block;font:15px Verdana,Arial,Sans-Serif;font-weight:bold;text-decoration:none}
#typepadantispamwrap #typepadantispamstats{background:url('.get_option('siteurl').'/wp-content/plugins/typepadantispam/typepadantispam.gif) no-repeat top left;border:none;font:11px \'Trebuchet MS\',\'Myriad Pro\',sans-serif;height:40px;line-height:100%;overflow:hidden;padding:3px 0 8px;text-align:center;width:120px}
</style>
';
 $count = number_format_i18n(get_option('typepad_antispam_spam_count'));
 echo '
<div id="typepadantispamwrap"><div id="typepadantispamstats"><a id="tpaa" href="http://antispam.typepad.com/" title=""><div id="typepadantispam1"><span id="typepadantispamcount">'.$count.'</span> <span id="typepadantispamsc">'._e('spam comments').'</span></div> <div id="typepadantispam2"><span id="typepadantispambb">'._e('blocked by').'</span><br /><span id="typepadantispama"><img src="'.get_option('siteurl').'/wp-content/plugins/'.TY_BUNDLE_DIR.'/typepadantispam-logo.gif" /></span></div></a></div></div>
';
    }




    private function _antispamRecheckQueue() {
        if ( !isset( $_GET['recheckqueue'] ) ){
            return;
        }
        $moderation = $this->_wpdb->get_results( "SELECT * FROM ".$this->_wpdb->comments." WHERE comment_approved = '0'", ARRAY_A );
        foreach ( $moderation as $c ) {
            $id = (int) $c['comment_ID'];
            $post_id = (int) $c['comment_post_ID'];
            $post = get_post($post_id);
            $c['user_ip']    = $c['comment_author_IP'];
            $c['user_agent'] = $c['comment_agent'];
            $c['referrer']   = '';
            $c['blog']       = get_option('home');
            $c['article_date'] = preg_replace('/\D/', '', $post->post_date);
            $query_string = '';
            foreach ( $c as $key => $data )
                $query_string .= $key . '=' . urlencode( stripslashes($data) ) . '&';

            $response = $this->_antispamHttpPost($query_string, $this->_antiSpamHost, '/'.TYPEPADANTISPAM_PROTOCOL_VER.'/comment-check', TYPEPADANTISPAM_API_PORT);
            if ( 'true' == $response[1] ) {
                $this->_wpdb->query( "UPDATE ".$this->_wpdb->comments." SET comment_approved = 'spam' WHERE comment_ID = $id" );
            }
        }
        wp_redirect( $_SERVER['HTTP_REFERER'] );
        exit;
    }


    public function antispamRightnow() {
        if ( isset( $this->_submenu['edit-comments.php'] ) ){
            $link = 'edit-comments.php';
        }
        else{
            $link = 'edit.php';
        }
        if ( $count = get_option('typepad_antispam_count') ) {
            $intro = sprintf( __ngettext(
                                         '<a href="%1$s">TypePad AntiSpam</a> has protected your site from %2$s spam comment already,',
                                         '<a href="%1$s">TypePad AntiSpam</a> has protected your site from %2$s spam comments already,',
                                         $count
                                         ), TYPEPADANTISPAM_SERVICE_URL, number_format_i18n( $count ) );
        } else {
            $intro = sprintf( __('<a href="%1$s">TypePad AntiSpam</a> blocks spam from getting to your blog,'), TYPEPADANTISPAM_SERVICE_URL );
        }

        if ( $queue_count = $this->_antispamCount() ) {
            $queue_text = sprintf( __ngettext(
                                              'and there\'s <a href="%2$s">%1$s comment</a> in your spam queue right now.',
                                              'and there are <a href="%2$s">%1$s comments</a> in your spam queue right now.',
                                              $queue_count
                                              ), number_format_i18n( $queue_count ), clean_url("$link?page=".TY_PAGE));
        } else {
            $queue_text = sprintf( __( "but there's nothing in your <a href='%1\$s'>spam queue</a> at the moment." ), clean_url("$link?page=".TY_PAGE) );
        }

        $text = sprintf( _c( '%1$s %2$s|typepadantispam_rightnow' ), $intro, $queue_text );

        echo "<p class='typepadantispam-right-now'>".$text."</p>\n";
    }



    public function antispamAutoCheckComment( $comment ) {
        $comment['user_ip']    = preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] );
        $comment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $comment['referrer']   = $_SERVER['HTTP_REFERER'];
        $comment['blog']       = get_option('home');
        $post_id = (int) $c['comment_post_ID'];
        $post = get_post( $post_id );
        $comment['article_date'] = preg_replace('/\D/', '', $post->post_date);
        $ignore = array( 'HTTP_COOKIE' );

        foreach ( $_SERVER as $key => $value ){
            if ( !in_array( $key, $ignore ) ){
                $comment["$key"] = $value;
            }
        }
        $query_string = '';
        foreach ( $comment as $key => $data ){
            $query_string .= $key . '=' . urlencode( stripslashes($data) ) . '&';
        }
        $response = $this->_antispamHttpPost($query_string, $this->_antiSpamHost, '/'.TYPEPADANTISPAM_PROTOCOL_VER.'/comment-check', TYPEPADANTISPAM_API_PORT);
        if ( 'true' == $response[1] ) {
            add_filter('pre_comment_approved', create_function('$a', 'return \'spam\';'));
            update_option( 'typepad_antispam_count', get_option('typepad_antispam_count') + 1 );
            do_action( 'typepadantispam_spam_caught' );  
            $last_updated = strtotime( $post->post_modified_gmt );
            $diff = time() - $last_updated;
            $diff = $diff / 86400;
            if ( $post->post_type == 'post' && $diff > 30 && get_option( 'typepad_antispam_trash' ) == 'true' ){
                // hmmmm
                die;
            }
        }
        $this->_antispamDeleteOld();
        return $comment;
    }

    

    private function _antispamDeleteOld() {
        $now_gmt = current_time('mysql', 1);
        $this->_wpdb->query("DELETE FROM ".$this->_wpdb->comments." WHERE DATE_SUB('".$now_gmt."', INTERVAL 15 DAY) > comment_date_gmt AND comment_approved = 'spam'");
        $n = mt_rand(1, 5000);
        if ( $n == 11 ){ // lucky number
            $this->_wpdb->query("OPTIMIZE TABLE ".$this->_wpdb->comments);
        }
    }

    private function _antispamSubmitNonspamComment ( $comment_id ) {
        $comment_id = (int) $comment_id;
        $comment = $this->_wpdb->get_row("SELECT * FROM ".$this->_wpdb->comments." WHERE comment_ID = '".$comment_id."'");
        if ( !$comment ){ // it was deleted
            return;
        }
        $comment->blog = get_option('home');
        $post_id = (int) $comment->comment_post_ID;
        $post = get_post( $post_id );
        if ( !$post ) // deleted
            return;
        $comment->article_date = preg_replace('/\D/', '', $post->post_date);
        $query_string = '';
        foreach ( $comment as $key => $data )
            $query_string .= $key . '=' . urlencode( stripslashes($data) ) . '&';
        $response = $this->_antispamHttpPost($query_string, $this->_antiSpamHost, '/'.TYPEPADANTISPAM_PROTOCOL_VER.'/submit-ham', TYPEPADANTISPAM_API_PORT);
    }

 
 public function antispamCaught() {
        $this->_antispamRecheckQueue();
        if (isset($_POST['submit']) && 'recover' == $_POST['action'] && ! empty($_POST['not_spam'])) {
            check_admin_referer( 'typepadantispam-update-key' );
            if ( function_exists('current_user_can') && !current_user_can('moderate_comments') ){
                die(__('You do not have sufficient permission to moderate comments.'));
            }
            
		$i = 0;
		foreach ($_POST['not_spam'] as $comment):
			$comment = (int) $comment;
        if ( function_exists('wp_set_comment_status') )
            wp_set_comment_status($comment, 'approve');
        else
            $this->_wpdb->query("UPDATE $this->_wpdb->comments SET comment_approved = '1' WHERE comment_ID = '$comment'");
        $this->_antispamSubmitNonspamComment($comment);
        ++$i;
		endforeach;
		$to = add_query_arg( 'recovered', $i, $_SERVER['HTTP_REFERER'] );
		wp_redirect( $to );
		exit;
	}
	if ('delete' == $_POST['action']) {
		check_admin_referer( 'typepadantispam-update-key' );
		if ( function_exists('current_user_can') && !current_user_can('moderate_comments') )
			die(__('You do not have sufficient permission to moderate comments.'));

		$delete_time = $this->_wpdb->escape( $_POST['display_time'] );
		$nuked = $this->_wpdb->query( "DELETE FROM ".$this->_wpdb->comments." WHERE comment_approved = 'spam' AND '".$delete_time."' > comment_date_gmt" );
		wp_cache_delete( 'typepad_antispam_count', 'widget' );
		$to = add_query_arg( 'deleted', 'all', $_SERVER['HTTP_REFERER'] );
		wp_redirect( $to );
		exit;
	}

    if ( isset( $_GET['recovered'] ) ) {
        $i = (int) $_GET['recovered'];
        echo '<div class="updated"><p>' . sprintf(__('%1$s comments recovered.'), $i) . "</p></div>";
    }

    if (isset( $_GET['deleted'] ) )
        echo '<div class="updated"><p>' . __('All spam deleted.') . '</p></div>';

    if ( !empty( $this->_submenu['edit-comments.php'] ) )
        $link = 'edit-comments.php';
    else
        $link = 'edit.php';
            ?>

<style type="text/css">
.typepadantispam-tabs {
	list-style: none;
	margin: 0;
	padding: 0;
	clear: both;
	border-bottom: 1px solid #ccc;
	height: 31px;
	margin-bottom: 20px;
	background: #ddd;
	border-top: 1px solid #bdbdbd;
}
.typepadantispam-tabs li {
	float: left;
	margin: 5px 0 0 20px;
}
.typepadantispam-tabs a {
	display: block;
	padding: 4px .5em 3px;
	border-bottom: none;
	color: #036;
}
.typepadantispam-tabs .active a {
	background: #fff;
	border: 1px solid #ccc;
	border-bottom: none;
	color: #000;
	font-weight: bold;
	padding-bottom: 4px;
}
#typepadantispamsearch {
	float: right;
	margin-top: -.5em;
}

#typepadantispamsearch p {
	margin: 0;
	padding: 0;
}
</style>
<div class="wrap">
<h2><?php _e('Caught Spam') ?></h2>
<?php
$count = get_option( 'typepad_antispam_count' );
if ( $count ) {
?>
<p><?php printf(__('TypePad AntiSpam has caught <strong>%1$s spam</strong> for you since you first installed it.'), number_format_i18n($count) ); ?></p>
<?php
     }

$spam_count = $this->_antispamCount();

if ( 0 == $spam_count ) {
	echo '<p>'.__('You have no spam currently in the queue. Must be your lucky day. :)').'</p>';
	echo '</div>';
} else {
	echo '<p>'.__('You can delete all of the spam from your database with a single click. This operation cannot be undone, so you may wish to check to ensure that no legitimate comments got through first. Spam is automatically deleted after 15 days, so don&#8217;t sweat it.').'</p>';
?>
<?php if ( !isset( $_POST['s'] ) ) { ?>
<form method="post" action="<?php echo attribute_escape( add_query_arg( 'noheader', 'true' ) ); ?>">
<?php wp_nonce_field('typepadantispam-update-key') ?>
<input type="hidden" name="action" value="delete" />
<?php printf(__('There are currently %1$s comments identified as spam.'), $spam_count); ?>&nbsp; &nbsp; <input type="submit" class="button delete" name="Submit" value="<?php _e('Delete all'); ?>" />
<input type="hidden" name="display_time" value="<?php echo current_time('mysql', 1); ?>" />
</form>
<?php } ?>
</div>
<div class="wrap">
<?php if ( isset( $_POST['s'] ) ) { ?>
<h2><?php _e('Search'); ?></h2>
<?php } else { ?>
<?php echo '<p>'.__('These are the latest comments identified as spam by TypePad AntiSpam. If you see any mistakes, simply mark the comment as "not spam" and TypePad AntiSpam will learn from the submission. If you wish to recover a comment from spam, simply select the comment, and click Not Spam. After 15 days we clean out the junk for you.').'</p>'; ?>
<?php } ?>
<?php
if ( isset( $_POST['s'] ) ) {
	$s = $this->_wpdb->escape($_POST['s']);
	$comments = $this->_wpdb->get_results("SELECT * FROM ".$this->_wpdb->comments."  WHERE
		(comment_author LIKE '%$s%' OR
		comment_author_email LIKE '%$s%' OR
		comment_author_url LIKE ('%$s%') OR
		comment_author_IP LIKE ('%$s%') OR
		comment_content LIKE ('%$s%') ) AND
		comment_approved = 'spam'
		ORDER BY comment_date DESC");
} else {
	if ( isset( $_GET['apage'] ) )
		$page = (int) $_GET['apage'];
	else
		$page = 1;

	if ( $page < 2 )
		$page = 1;

	$current_type = false;
	if ( isset( $_GET['ctype'] ) )
		$current_type = preg_replace( '|[^a-z]|', '', $_GET['ctype'] );

	$comments = $this->_antispamSpamComments( $current_type, $page );
	$total = $this->_antispamCount( $current_type );
	$totals = $this->_antispamSpamTotals();
?>
<ul class="typepadantispam-tabs">
<li <?php if ( !isset( $_GET['ctype'] ) ) echo ' class="active"'; ?>><a href="edit.php?page=<?php echo TY_PAGE; ?>"><?php _e('All'); ?></a></li>
<?php
foreach ( $totals as $type => $type_count ) {
	if ( 'comment' == $type ) {
		$type = 'comments';
		$show = __('Comments');
	} else {
		$show = ucwords( $type );
	}
	$type_count = number_format_i18n( $type_count );
	$extra = $current_type === $type ? ' class="active"' : '';
	echo "<li $extra><a href='edit.php?page=".TY_PAGE."&amp;ctype=$type'>$show ($type_count)</a></li>";
}
do_action( 'typepadantispam_tabs' ); // so plugins can add more tabs easily
?>
</ul>
<?php
}

if ($comments) {
?>
<form method="post" action="<?php echo attribute_escape("$link?page=".TY_PAGE); ?>" id="typepadantispamsearch">
<p>  <input type="text" name="s" value="<?php if (isset($_POST['s'])) echo attribute_escape($_POST['s']); ?>" size="17" />
  <input type="submit" class="button" name="submit" value="<?php echo attribute_escape(__('Search Spam &raquo;')) ?>"  />  </p>
</form>
<?php if ( $total > 50 ) {
$total_pages = ceil( $total / 50 );
$r = '';
if ( 1 < $page ) {
	$args['apage'] = ( 1 == $page - 1 ) ? '' : $page - 1;
	$r .=  '<a class="prev" href="' . clean_url(add_query_arg( $args )) . '">'. __('&laquo; Previous Page') .'</a>' . "\n";
}
if ( ( $total_pages = ceil( $total / 50 ) ) > 1 ) {
	for ( $page_num = 1; $page_num <= $total_pages; $page_num++ ) :
		if ( $page == $page_num ) :
			$r .=  "<strong>$page_num</strong>\n";
		else :
			$p = false;
			if ( $page_num < 3 || ( $page_num >= $page - 3 && $page_num <= $page + 3 ) || $page_num > $total_pages - 3 ) :
				$args['apage'] = ( 1 == $page_num ) ? '' : $page_num;
				$r .= '<a class="page-numbers" href="' . clean_url(add_query_arg($args)) . '">' . ( $page_num ) . "</a>\n";
				$in = true;
			elseif ( $in == true ) :
				$r .= "...\n";
				$in = false;
			endif;
		endif;
	endfor;
}
if ( ( $page ) * 50 < $total || -1 == $total ) {
	$args['apage'] = $page + 1;
	$r .=  '<a class="next" href="' . clean_url(add_query_arg($args)) . '">'. __('Next Page &raquo;') .'</a>' . "\n";
}
echo "<p>$r</p>";
?>

<?php } ?>
<form style="clear: both;" method="post" action="<?php echo attribute_escape( add_query_arg( 'noheader', 'true' ) ); ?>">
<?php wp_nonce_field('typepadantispam-update-key') ?>
<input type="hidden" name="action" value="recover" />
<ul id="spam-list" class="commentlist" style="list-style: none; margin: 0; padding: 0;">
<?php
$i = 0;
foreach($comments as $comment) {
	$i++;
	$comment_date = mysql2date(get_option("date_format") . " @ " . get_option("time_format"), $comment->comment_date);
	$post = get_post($comment->comment_post_ID);
	$post_title = $post->post_title;
	if ($i % 2) $class = 'class="alternate"';
	else $class = '';
	echo "\n\t<li id='comment-$comment->comment_ID' $class>";
	?>

<p><strong><?php echo $comment->comment_author; ?></strong> <?php if ($comment->comment_author_email) { ?>| <?php comment_author_email_link() ?> <?php } if ($comment->comment_author_url && 'http://' != $comment->comment_author_url) { ?> | <?php comment_author_url_link() ?> <?php } ?>| <?php _e('IP:') ?> <a href="http://ws.arin.net/cgi-bin/whois.pl?queryinput=<?php echo $comment->comment_author_IP; ?>"><?php echo $comment->comment_author_IP; ?></a></p>

<?php echo $comment->comment_content; ?>

<p><label for="spam-<?php echo $comment->comment_ID; ?>">
<input type="checkbox" id="spam-<?php echo $comment->comment_ID; ?>" name="not_spam[]" value="<?php echo $comment->comment_ID; ?>" />
<?php _e('Not Spam') ?></label> &#8212; <?php comment_date('M j, g:i A');  ?> &#8212; [
<?php
// $post = get_post($comment->comment_post_ID); # redundant? $post already set
$post_title = wp_specialchars( $post->post_title, 'double' );
$post_title = ('' == $post_title) ? "# $comment->comment_post_ID" : $post_title;
?>
 <a href="<?php echo get_permalink($comment->comment_post_ID); ?>" title="<?php echo $post_title; ?>"><?php _e('View Post') ?></a> ] </p>


<?php
                                                                                          }
?>
</ul>
<?php if ( $total > 50 ) {
$total_pages = ceil( $total / 50 );
$r = '';
if ( 1 < $page ) {
	$args['apage'] = ( 1 == $page - 1 ) ? '' : $page - 1;
	$r .=  '<a class="prev" href="' . clean_url(add_query_arg( $args )) . '">'. __('&laquo; Previous Page') .'</a>' . "\n";
}
if ( ( $total_pages = ceil( $total / 50 ) ) > 1 ) {
	for ( $page_num = 1; $page_num <= $total_pages; $page_num++ ) :
		if ( $page == $page_num ) :
			$r .=  "<strong>$page_num</strong>\n";
		else :
			$p = false;
			if ( $page_num < 3 || ( $page_num >= $page - 3 && $page_num <= $page + 3 ) || $page_num > $total_pages - 3 ) :
				$args['apage'] = ( 1 == $page_num ) ? '' : $page_num;
				$r .= '<a class="page-numbers" href="' . clean_url(add_query_arg($args)) . '">' . ( $page_num ) . "</a>\n";
				$in = true;
			elseif ( $in == true ) :
				$r .= "...\n";
				$in = false;
			endif;
		endif;
	endfor;
}
if ( ( $page ) * 50 < $total || -1 == $total ) {
	$args['apage'] = $page + 1;
	$r .=  '<a class="next" href="' . clean_url(add_query_arg($args)) . '">'. __('Next Page &raquo;') .'</a>' . "\n";
}
echo "<p>$r</p>";
}
?>
<p class="submit">
<input type="submit" name="submit" value="<?php echo attribute_escape(__('De-spam marked comments &raquo;')); ?>" />
</p>
<p><?php _e('Comments you de-spam will be submitted to TypePad AntiSpam as mistakes so it can learn and get better.'); ?></p>
</form>
<?php
} else {
?>
<p><?php _e('No results found.'); ?></p>
<?php } ?>

<?php if ( !isset( $_POST['s'] ) ) { ?>
<form method="post" action="<?php echo attribute_escape( add_query_arg( 'noheader', 'true' ) ); ?>">
<?php wp_nonce_field('typepadantispam-update-key') ?>
<p><input type="hidden" name="action" value="delete" />
<?php printf(__('There are currently %1$s comments identified as spam.'), $spam_count); ?>&nbsp; &nbsp; <input type="submit" name="Submit" class="button" value="<?php echo attribute_escape(__('Delete all')); ?>" />
<input type="hidden" name="display_time" value="<?php echo current_time('mysql', 1); ?>" /></p>
</form>
<?php } ?>
</div>
<?php
	}
}




private function _antispamSpamComments( $type = false, $page = 1, $per_page = 50 ) {
	$page = (int) $page;
	if ( $page < 2 ){
		$page = 1;
    }
	$per_page = (int) $per_page;
	if ( $per_page < 1 ){
		$per_page = 50;
    }
	$start = ( $page - 1 ) * $per_page;
	$end = $start + $per_page;

	if ( $type ) {
		if ( 'comments' == $type || 'comment' == $type )
			$type = '';
		else
			$type = $this->_wpdb->escape( $type );
		return $this->_wpdb->get_results( "SELECT * FROM ".$this->_wpdb->comments." WHERE comment_approved = 'spam' AND comment_type='$type' ORDER BY comment_date DESC LIMIT $start, $end");
	}

	// All
	return $this->_wpdb->get_results( "SELECT * FROM ".$this->_wpdb->comments." WHERE comment_approved = 'spam' ORDER BY comment_date DESC LIMIT $start, $end");
}


// Totals for each comment type
// returns array( type => count, ... )
private function _antispamSpamTotals() {
	$totals = $this->_wpdb->get_results( "SELECT comment_type, COUNT(*) AS cc FROM ".$this->_wpdb->comments." WHERE comment_approved = 'spam' GROUP BY comment_type" );
	$return = array();
	foreach ( $totals as $total )
		$return[$total->comment_type ? $total->comment_type : 'comment'] = $total->cc;
	return $return;
}

public function antispamManagePage() {
	$count = sprintf(__('TypePad AntiSpam Spam (%s)'), $this->_antispamCount());
	if ( isset( $this->_submenu['edit-comments.php'] ) )
		add_submenu_page('edit-comments.php', __('TypePad AntiSpam Spam'), $count, 'moderate_comments', TY_PAGE , array(&$this, 'antispamCaught'));
	elseif ( function_exists('add_management_page') )
		add_management_page(__('TypePad AntiSpam Spam'), $count, 'moderate_comments', TY_PAGE , array(&$this, 'antispamCaught'));
}


// For WP >= 2.5
public function antispamCheckForSpamButton($comment_status) {
	if ( 'moderated' != $comment_status )
		return;
	$count = wp_count_comments();
	if ( !empty($count->moderated ) )
		echo "<a href='edit.php?page=".TY_PAGE."&amp;recheckqueue=true&amp;noheader=true'>" . __('Check for Spam') . "</a>";
}

// Widget stuff
 function widgetAntispamRegister() {
     if ( function_exists('register_sidebar_widget') ) :
         function widget_typepadantispam($args) {
         extract($args);
         $options = get_option('widget_typepadantispam');
         $count = number_format_i18n(get_option('typepad_antispam_count'));
             ?>
             <?php echo $before_widget; ?>
                   <?php echo $before_title . $options['title'] . $after_title; ?>
                         <div id="typepadantispamwrap"><div id="typepadantispamstats"><a id="tpaa" href="http://antispam.typepad.com/" title=""><div id="typepadantispam1"><span id="typepadantispamcount"><?php echo  $count; ?></span><span id="typepadantispamsc"><?php echo _e('spam comments'); ?></span></div><div id="typepadantispam2"><span id="typepadantispambb"></span><span id="typepadantispama"></span></div><div id="typepadantispam2"><span id="typepadantispambb"><?php _e('blocked by') ?></span><br /><span id="typepadantispama"><img src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/typepadantispam/typepadantispam-logo.gif" /></span></div></a></div></div>
                                                                                                                                                                                                                                                                                                             <?php echo $after_widget; ?>
                                                                                                                                                                                                                                                                                                             <?php
                                                                                                                                                                                                                                                                                                                   }

     function widget_typepadantispam_style() {
    ?>
 <style type="text/css">
#typepadantispamwrap #tpaa,#tpaa:link,#tpaa:hover,#tpaa:visited,#tpaa:active{text-decoration:none}
#tpaa:hover{border:none;text-decoration:none}
#tpaa:hover #typepadantispam1{display:none}
#tpaa:hover #typepadantispam2,#typepadantispam1{display:block}
#typepadantispam1{padding-top:5px;}
#typepadantispam2{display:none;padding-top:0px;color:#333;}
#typepadantispama{font-size:16px;font-weight:bold;line-height:18px;text-decoration:none;}
#typepadantispamcount{display:block;font:15px Verdana,Arial,Sans-Serif;font-weight:bold;text-decoration:none}
#typepadantispamwrap #typepadantispamstats{background:url(<?php echo get_option('siteurl'); ?>/wp-content/plugins/typepadantispam/typepadantispam.gif) no-repeat top left;border:none;font:11px 'Trebuchet MS','Myriad Pro',sans-serif;height:40px;line-height:100%;overflow:hidden;padding:3px 0 8px;text-align:center;width:120px}
      </style>
      <?php
      }

     function widget_typepadantispam_control() {
         $options = $newoptions = get_option('widget_typepadantispam');
         if ( $_POST["typepadantispam-submit"] ) {
             $newoptions['title'] = strip_tags(stripslashes($_POST["typepadantispam-title"]));
             if ( empty($newoptions['title']) ) $newoptions['title'] = 'Spam Blocked';
         }
         if ( $options != $newoptions ) {
             $options = $newoptions;
             update_option('widget_typepadantispam', $options);
         }
         $title = htmlspecialchars($options['title'], ENT_QUOTES);
             ?>
             <p><label for="typepadantispam-title"><?php _e('Title:'); ?> <input style="width: 250px;" id="typepadantispam-title" name="typepadantispam-title" type="text" value="<?php echo $title; ?>" /></label></p>
                                                                               <input type="hidden" id="typepadantispam-submit" name="typepadantispam-submit" value="1" />
                                                                               <?php
                                                                               }

     register_sidebar_widget('TypePad AntiSpam', 'widget_typepadantispam', null, 'typepadantispam');
     register_widget_control('TypePad AntiSpam', 'widget_typepadantispam_control', null, 75, 'typepadantispam');
     if ( is_active_widget('widget_typepadantispam') )
         add_action('wp_head', 'widget_typepadantispam_style');
     endif;
 }


}

/**
 * create the instance
 *
 */
function typePadInit() {
    global $typepad;
    $typepad = new typePadBundle(); 
	function addTypePadAd($id,$size){
        global $typepad;
        return $typepad->mediaChoice($id,$size);
    }
}



add_action('plugins_loaded', 'typePadInit');



?>
