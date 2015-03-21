<?php
// Single Platform Syncing Credentials
$signingKey = "thbcjBdQzEhR1e8GC9NAAfgsernAhJJwl_ynp8BClt4";
$clientId = "cqrghvdbpdq2ittuls3tviyu9";

// Starting Data Manipulation from Single Platform API
$debug = false;
$query = '';
$host = 'api.singleplatform.co';
$protocol = 'http';
$limit = 1000;
$error_msg = '';
$j = 0;	

$sp_api = new SP_ApiLibrary( $signingKey, $clientId, $host, $protocol, $debug );
$result = $sp_api->searchLocation( $query, 0, 1 );


if(isset($_POST['submit']))
{
	//print_r($_POST);
	$page_no = sanitize_text_field($_POST['pagelist']);
		
	$venue = $sp_api->searchLocation( $query, $page_no, $limit );
		
	// For wp_posts tables
	for($i = 0; $i < $limit; $i++)
	{
		//trim( $venue['results'][$i]['id'], '- ' ) . "<br>";	
		$user_ID = get_current_user_id(); 
		$postmeta = array();
		$businessSite = array();
		
		$post_name = '';
		$post_name = clean(trim($venue['results'][$i]['id'], '-'));
		$check_post_exists = wp_exist_post_by_name($post_name);
		
		if(is_null($check_post_exists))
		{			
			$venue_post = array(
				'post_author'   => $user_ID,
				'post_content'	=> $venue['results'][$i]['general']['desc'],
			  	'post_title'    => $venue['results'][$i]['general']['name'],
			  	'post_name'		=> $post_name,
			  	'post_excerpt'  => serialize($venue['results'][$i]['hours']),
			  	'post_status'   => 'publish',		  
			  	'post_type' 	=> 'wpbdp_listing'
			);

			// Insert the post into the wp_posts table
			$postID = wp_insert_post( $venue_post );		
			
			if($postID)
			{
				// Insert related data into the wp_postmeta table
				$postmeta['_wpbdp[fields][10]'] = $venue['results'][$i]['location']['address1'];	// Business Address 1	
				$postmeta['_wpbdp[fields][11]'] = $venue['results'][$i]['location']['address2'];	// Business Address 2		
				$businessSite[] = $venue['results'][$i]['general']['website'];
				$postmeta['_wpbdp[fields][5]'] = serialize($businessSite);							// Business Website
				$postmeta['_wpbdp[fields][6]'] = $venue['results'][$i]['phones']['main'];			// Business Phone
				$postmeta['_wpbdp[fields][12]'] = $venue['results'][$i]['location']['city'];		// Business City
				$postmeta['_wpbdp[fields][13]'] = $venue['results'][$i]['location']['region'];		// Business State
				$postmeta['_wpbdp[fields][14]'] = $venue['results'][$i]['location']['postcode'];	// Business Zip
				$postmeta['_wpbdp[fields][16]'] = '';												// Business Video 1
				$postmeta['_wpbdp[fields][17]'] = '';												// Business Video 2
				$postmeta['_wpbdp[fields][18]'] = '';												// Business Video 3
				$postmeta['_wpbdp[fields][19]'] = '';												// Business Video 4
				$postmeta['_wpbdp[fields][20]'] = '';												// Business Video 5
				$postmeta['_wpbdp[fields][21]'] = '';												// Coupon Logo
				$postmeta['_wpbdp[fields][22]'] = $venue['results'][$i]['location']['latitude'];	// Business Latitude
				$postmeta['_wpbdp[fields][23]'] = $venue['results'][$i]['location']['longitude'];	// Business Longitude
				$postmeta['_wpbdp[thumbnail_id]'] = '';												// Profile Picture

				// Insert post meta.
				foreach ( $postmeta as $key => $value ) {
					add_post_meta( $postID, $key, $value, true );
				}			
								
				$term_name = $venue['results'][$i]['businessType'];
				$slug = clean(strtolower($term_name));
				if(! term_exists( $slug, 'wpbdp_category' ) )
				{
					// Insert category in wp_terms table
					wp_insert_term(
					  $term_name, // the term 
					  'wpbdp_category', // the taxonomy
					  array(
					    'description'=> '',
					    'slug' => $slug,
					    'parent'=> 0
					  )
					);	  
					
					// Insert data into wp_term_taxonomy and wp_term_relationship table
					$termdata = term_exists( $slug, 'wpbdp_category' );
					$term_taxonomy_ids = wp_set_object_terms( $postID, $slug, 'wpbdp_category' );				
				}
				else
				{
					$termdata = term_exists( $slug, 'wpbdp_category' );
					$term_taxonomy_ids = wp_set_object_terms( $postID, $slug, 'wpbdp_category' );					
				}
			}
			else
			{
				$error_msg.= 'There was an error processing after creating post. Please try again later.' . '<br>';
				break;
			}	
			$j++; 	// It is used for counting total successful inserted data. 		
		}
		else
		{
			//echo 'Post exists!<br>';
		}
	}	
	
	if($error_msg != '')
	{
?>
		<div class="error"><p><?php echo $error_msg; ?></p></div>	
<?php
	}
	else
	{
		$msg_class = ($j == 0)?'error':'updated';

?>
			<div class="<?php echo $msg_class; ?>">
				<p><?php echo "Total Successful insert: $j & unsuccessful transfer: " . ($limit - $j) . '<br>'; ?></p>
			</div>		
<?php
	}
}
?>

<h2>Single Platform API Data Feed Generation</h2>
<h4><?php echo 'Total Business Listings found: ' . $result['total']; ?></h4>
<form name="listgenform" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	<?php echo 'Select Page: '; ?>
	<select name="pagelist">
		<?php
			for($i = 0; $i < ceil(($result['total']) / 1000); $i++)
			{
		?>
				<option value="<?php echo $i; ?>">Page <?php echo $i; ?></option>
		<?php
			}	
		?>	
	</select>
	<input type="submit" name="submit" value="Insert into Database" class="button button-primary"/>
</form>



<?php

// Plugin related functions
function wp_exist_post_by_name($name_str) {
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM wp_posts WHERE post_name = '" . $name_str . "' and post_type = 'wpbdp_listing' and post_status='publish'", 'ARRAY_A');
}

function clean($string) {
   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
   $string = preg_replace('/[^A-Za-z0-9.\-]/', '', $string); // Removes special chars.

   return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
}
?>
