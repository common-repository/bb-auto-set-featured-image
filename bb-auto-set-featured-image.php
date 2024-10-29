<?php
/*
Plugin Name: BB Auto Set Featured Image
Description: This plugin automaticaly sets featured image for your blog posts if post thumbnail is not set manually. This extracts keywords from your article title and content and searches for image in pixabay.com. 
Version: 1.0.0
Author: blogforbloggers
Author URI:   https://profiles.wordpress.org/blogforbloggers/
License: GPLv2 or later
*/


if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}


define( 'AUTO_SET_FEATURED_IMAGE_VERSION', '1.0.0' );
define( 'AUTO_SET_FEATURED_IMAGE_DIR', plugin_dir_path( __FILE__ ) );


function bbafi_check_featurimagecheck( $post_id ) {

	// If this is just a revision, don't send the email.
	if ( wp_is_post_revision( $post_id ) )
            return;

        if(has_post_thumbnail($post_id)){
            
           return; 
        }
        
        $content_post = get_post($post_id);
        $post_content = $content_post->post_content;
        $keywords = bbafi_generate_keywords_from_text($post_content);
        
        if(count($keywords) >0 ){
            
                $random_key = array_rand($keywords, 1);
                $pixaimages = bbafi_pixabay($random_key);

                if(count($pixaimages) > 0){

                     $random_key = array_rand($pixaimages, 1);
                     $image = $pixaimages[$random_key]['image'];
                     bbafi_media_sideload_image($image,$post_id);


                }
        
        }
        
}


function bbafi_generate_keywords_from_text($text){
	
	
	    // List of words NOT to be included in keywords
      $stopWords = array('i','a','about','an','and','are','as','at','be','by','com','de','en','for','from','how','in','is','it','la','of','on','or','that','the','this','to','was','what','when','where','who','will','with','und','the','www', "such", "have", "then");
   
   
      //Let us do some basic clean up! on the text before getting to real keyword generation work
      $text = strip_tags( $text );
      $text = preg_replace('/\s\s+/i', '', $text); // replace multiple spaces etc. in the text
      $text = trim($text); // trim any extra spaces at start or end of the text
      $text = preg_replace('/[^a-zA-Z0-9 -]/', '', $text); // only take alphanumerical characters, but keep the spaces and dashes tooâ€¦
      $text = strtolower($text); // Make the text lowercase so that output is in lowercase and whole operation is case in sensitive.
   
      // Find all words
      preg_match_all('/\b.*?\b/i', $text, $allTheWords);
      $allTheWords = $allTheWords[0];
      
	    //Now loop through the whole list and remove smaller or empty words
      foreach ( $allTheWords as $key => $item ) {
          
          $item = trim($item);
          
          if ( $item == '' || in_array(strtolower($item), $stopWords) || strlen($item) <= 3 ) {
              
              unset($allTheWords[$key]);
              
          }
          else
          {
              $allTheWords[$key] = $item;
          }
      }   
	  
	    // Create array that will later have its index as keyword and value as keyword count.
      $wordCountArr = array();
	  
	    // Now populate this array with keywrds and the occurance count
      if ( is_array($allTheWords) ) {
          foreach ( $allTheWords as $key => $val ) {
              $val = strtolower($val);
              if ( isset($wordCountArr[$val]) ) {
                  $wordCountArr[$val]++;
              } else {
                  $wordCountArr[$val] = 1;
              }
          }
      }
	  
	    // Sort array by the number of repetitions
      arsort($wordCountArr);
	  
	    //Keep first 10 keywords, throw other keywords
      $wordCountArr = array_slice($wordCountArr, 0, 10);
	  
  return $wordCountArr;
}


function bbafi_pixabay($keywords) {
    
     
        
        $keywords = str_replace(' ','+',$keywords);
        $apikey   = "6855592-14276b9cbb58e5c2554e46166";
      
        $limit  = 10;
        $offset = 1;
           
        
        $url = "https://pixabay.com/api/?key=".$apikey."&q=".$keywords."&per_page=".$limit."&page=".$offset."&image_type=photo";
        
        $pixaImages = json_decode(file_get_contents($url),true);
        

        $data = array();  
        if(count($pixaImages['hits']) > 0)
        {

            $data['status'] = 1;
            foreach($pixaImages['hits'] as $images){  
            
               $data[] = array(
                                        'image'  => $images['webformatURL'],
                                        'id'     => $images['id'],
                                        'type'   => 'image'
                                      );
            
            }
        
        } 
        
        
        return $data;

}


function bbafi_media_sideload_image($url, $post_id, $filename = NULL) {

            require_once( ABSPATH . 'wp-admin/includes/file.php' );

            $tmp = download_url($url);
            if (is_wp_error($tmp)) {
                // And output wp_error.
                return array('status' => 'false', 'message' => 'An Unknown error occurred while uploading media file.');
            }

            preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
            $url_filename = basename($matches[0]);
            $url_type = wp_check_filetype($url_filename);


            if (!empty($filename)) {
                $filename = sanitize_file_name($filename);
                $tmppath = pathinfo($tmp);
                $new = $tmppath['dirname'] . '/' . $filename . '.' . $tmppath['extension'];
                rename($tmp, $new);
                $tmp = $new;
            }
            $file_array['tmp_name'] = $tmp;

            if (!empty($filename)) {

                $file_array['name'] = $filename . '.' . $url_type['ext'];
            } else {

                $file_array['name'] = $url_filename;
            }


            $post_data = array(
                'post_title' => get_the_title($post_id),
                'post_parent' => $post_id,
                'post_mime_type' => $url_type['type'],
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Required libraries for media_handle_sideload.
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );


            // $post_data can override the items saved to wp_posts table, like post_mime_type, guid, post_parent, post_title, post_content, post_status.
            $att_id = media_handle_sideload($file_array, $post_id, null, $post_data);

          
            // If error storing permanently, unlink.
            if (is_wp_error($att_id)) {
                // Clean up.
                @unlink($file_array['tmp_name']);

                // And output wp_error.
                return array('status' => 'false', 'message' => 'An Unknown error occurred while uploading media file.');
            }

         
            set_post_thumbnail($post_id, $att_id);
            return array('status' => 'true');
}

//attach hook to call after post is saved
add_action( 'save_post', 'bbafi_check_featurimagecheck' );
