<?php

/*
Plugin Name: Gravity Forms - Announcements
Description: Adds custom functionality to Submit Announcements form, such as notifying submitter when announcement is published and allowing submitter to schedule the announcement by date field.
Version: 1.0
Author: Jessica Foster
Author URI: https://imaginehigher.com
*/

/**
* Notify User When Submitted Announcement is Published
* https://gravitywiz.com/notify-user-when-submitted-post-is-published/
*/

add_action('publish_announcement', 'notify_on_publish');
function notify_on_publish($post_id) {
    global $post;
    
    $custom_field_name = 'submitter_email';
    $from_name = 'Spartanburg Methodist College';
    $from_email = 'warel@smcsc.edu';
    $subject = 'Your announcement has been approved';
    $message = 'Congratulations! Your announcement has been approved. It will appear in the next Daily Announcements email.';
    
    /* No need to edit beyond this point */
    
    // if this meta key is not set, this post was not created by a Gravity Form
    if (!get_post_meta($post_id, '_gform-form-id', true)) {
        return;
    }
    // make sure we havne't already sent a notification for this post
    if (get_post_meta($post_id, '_gform-notified', true)) {
        return;
    }
    
    $email = get_post_meta($post_id, $custom_field_name, true);
    
    $headers = "From: '$from_name' <$from_email> rn";
    $headers .= "Content-type: text/html; charset=" . get_option('blog_charset') . "rn";
    
    wp_mail($email, $subject, $message, $headers);
    
    update_post_meta($post_id, '_gform-notified', 1);
    
}


/**
 * Gravity Wiz // Gravity Forms // Schedule a Post by Date Field
 *
 * Schedule your Gravity Form generated posts to be published at a future date, specified by the user via GF Date and Time fields.
 *
 * @version	  1.0
 * @author    David Smith <david@gravitywiz.com>
 * @license   GPL-2.0+
 * @link      http://gravitywiz.com/...
 */

add_filter( 'gform_post_data_30', 'gw_schedule_post_by_date_field', 10, 3 );
function gw_schedule_post_by_date_field( $post_data, $form, $entry ) {

    $date = $entry['16']; // ID of your Date field
    $time = '08:50 am';

    ### don't touch the magic below this line ###

    if( empty( $date ) ) {
        return $post_data;
    }

    if( $time ) {
        list( $hour, $min, $am_pm ) = array_pad( preg_split( '/[: ]/', $time ), 3, false );
        if( strtolower( $am_pm ) == 'pm' ) {
            $hour += 12;
        }
    } else {
        $hour = $min = '00';
    }

    $schedule_date = date( 'Y-m-d H:i:s', strtotime( sprintf( '%s %s:%s:00', $date, $hour, $min ) ) );

    $post_data['post_status']   = 'pending';
    $post_data['post_date']     = $schedule_date;
    $post_data['post_date_gmt'] = get_gmt_from_date( $schedule_date );
    $post_data['edit_date']     = true;

    return $post_data;
}

/**
* Set expiration date for announcement after submission
*/

add_action( 'gform_after_submission_30', 'set_announcement_expiration', 10, 2 );
function set_announcement_expiration( $post_data, $form, $entry ) { 
    $post_id = $entry['post_id']; // get post id
    $user_date = $entry['25']; // get user-specified expiration date
    $schedule_date = $post_data['post_date']; // get schedule date as string
    $max_run = date('Y-n-d', strtotime($schedule_date. ' +3 days')); // add 3 days to schedule date as maximum run time
    
    if ( $user_date ) { // if user enters expiration date
        
        if ( $user_date <= $max_run ) { // if user-specified expiration date is 3 days or less from schedule date
        
            $expiration_date = date( 'Y-n-d', strtotime( $user_date ) ); // set user date as expiration date
        
        } else { // if user date is greater than 3 days from schedule date
            
            $expiration_date = $max_run; // set expiration date at 3 days from schedule date
        
        }
        
    } else { // if user does not enter expiration date
        
        $expiration_date = $max_run; // set expiration date at 3 days from schedule date
    
    }
    
    update_post_meta( $post_id, 'pw_spe_expiration', $expiration_date ); // update post meta with expiration date
}

/**
* Schedule announcement deletion upon expiration
*/

add_action( 'added_post_meta', 'schedule_announcement_deletion', 10, 4 );
add_action( 'updated_post_meta', 'schedule_announcement_deletion', 10, 4 );

function schedule_announcement_deletion( $meta_id, $post_id, $meta_key, $meta_value )
{
    if ( 'pw_spe_expiration' == $meta_key ) {
        wp_clear_scheduled_hook( 'schedule_announcement_deletion_action', array($post_id) );
        $expires = strtotime( $meta_value, current_time( 'timestamp' ) );
        wp_schedule_single_event($expires, 'schedule_announcement_deletion_action', array($post_id));
    }
}

add_action( 'deleted_post_meta', 'unschedule_announcement_deletion', 10, 4 );
function unschedule_announcement_deletion( $deleted_meta_ids, $post_id, $meta_key )
{
    if ( 'pw_spe_expiration' == $meta_key ) {
    	wp_clear_scheduled_hook( 'schedule_announcement_deletion_action', array($post_id) );
    }
}

add_action('schedule_announcement_deletion_action', 'wp_trash_post', 10, 1);
