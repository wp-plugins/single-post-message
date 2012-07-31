<?php
/*
Plugin Name: Single Post Message
Plugin URI: http://tommcfarlin.com/projects/single-post-message
Description: Easily add short messages and announcements above posts. Displays in the RSS feed and on the blog.
Version: 1.0
Author: Tom McFarlin
Author URI: http://tommcfarlin.com
Author Email: tom@tommcfarlin.com
License:

  Copyright 2012 Tom McFarlin (tom@tommcfarlin.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

class Single_Post_Message {

	// We write these out inline so that it is also styled in RSS readers
	private $styles = 'background-color:#fcf8e3;border:1px solid #fbeed5;border-radius:4px;-moz-border-radius:4px;-webkit-border-radius:4px;margin-bottom:18px;margin:8px 0 8px 0;color:#c09853;padding:12px;text-shadow:0 1px 0 rgba(255, 255, 255, 0.5);font-family:Arial,Sans-serif;font-size:12px;';
	 
	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	
	/**
	 * Initializes the plugin by setting localization, admin styles, and content filters.
	 */
	function __construct() {
	
		load_plugin_textdomain( 'single-post-message', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	
		// Include admin styles
		add_action( 'admin_print_styles', array( &$this, 'add_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'add_admin_scripts' ) );
		
		// Add the post meta box to the post editor
		add_action( 'add_meta_boxes', array( &$this, 'add_notice_metabox' ) );
		add_action( 'save_post', array( &$this, 'save_notice' ) );
		
		// Append the notice before the content in both the blog and in the feed.
	    add_filter( 'the_content', array( &$this, 'prepend_single_post_message' ) );

	} // end constructor
	
	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/
	 
	/**
 	 * Introduces the admin styles
 	 */
 	 function add_admin_styles() {
	 	 
	 	 wp_register_style( 'single-post-message', plugins_url() . '/single-post-message/css/admin.css' );
	 	 wp_enqueue_style( 'single-post-message' );
	 	 
 	 } // end add_admin_styles
	
	/**
 	 * Introduces the admin scripts
 	 */
 	 function add_admin_scripts() {
	 	 
	 	 wp_register_script( 'single-post-message', plugins_url() . '/single-post-message/js/admin.min.js' );
	 	 wp_enqueue_script( 'single-post-message' );
	 	 
 	 } // end add_admin_styles
	
	/**
	 * Adds the meta box below the post content editor on the post edit dashboard.
	 */
	 function add_notice_metabox() {
	
		add_meta_box(
			'single_post_message',
			__( 'Post Message', 'single-post-message' ),
			array( &$this, 'single_post_message_display' ),
			'post',
			'normal',
			'high'
		);

	} // end add_notice_metabox
	
	/**
	 * Renders the nonce and the textarea for the notice.
	 */
	function single_post_message_display( $post ) {
		
		wp_nonce_field( plugin_basename( __FILE__ ), 'single_post_message_nonce' );

		$html = '<textarea id="single-post-message" name="single_post_message" placeholder="' . __( 'Enter your post message here. HTML accepted.', 'single-post-message' ) . '">' . get_post_meta( $post->ID, 'single_post_message', true ) . '</textarea>';
		
		$html .= '<p id="single-post-message-preview">';
			
			if( '' == ( $post_message = get_post_meta( get_the_ID(), 'single_post_message', true ) ) ) {
				$html .= '<span id="single-post-message-default">' . __( 'Your post message preview will appear here.', 'single-post-message' ) . '</span>'; 
			} else {
				$html .= $post_message;
			} // end if/else
			
		$html .= '</p><!-- /#single-post-message-preview -->';
		
		echo $html;
		
	} // end standard_notice_display
	
	/**
	 * Saves the notice for the given post.
	 *
	 * @params	$post_id	The ID of the post that we're serializing
	 */
	function save_notice( $post_id ) {
		
		if( isset( $_POST['single_post_message_nonce'] ) && isset( $_POST['post_type'] ) ) {
		
			// Don't save if the user hasn't submitted the changes
			if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			} // end if
			
			// Verify that the input is coming from the proper form
			if( ! wp_verify_nonce( $_POST['single_post_message_nonce'], plugin_basename( __FILE__ ) ) ) {
				return;
			} // end if
			
			// Make sure the user has permissions to post
			if( 'post' == $_POST['post_type']) {
				if( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				} // end if
			} // end if/else
		
			// Read the post message
			$post_message = isset( $_POST['single_post_message'] ) ? $_POST['single_post_message'] : '';
			
			// If the value exists, delete it first. I don't want to write extra rows into the table.
			if ( 0 == count( get_post_meta( $post_id, 'single_post_message' ) ) ) {
				delete_post_meta( $post_id, 'single_post_message' );
			} // end if
	
			// Update it for this post.
			update_post_meta( $post_id, 'single_post_message', $post_message );
	
		} // end if
	
	} // end save_notice
	
	/**
 	 * Prepends the content with the notice, if specified.
 	 *
 	 * @params	$content	The post content.
 	 * @returns				The post content with the prepended notice (if specified).
	 */
	function prepend_single_post_message( $content ) {
    	
    	// If there is a notice, prepend it to the content
    	if( '' != get_post_meta( get_the_ID(), 'single_post_message', true ) ) {
    	
	    	$post_message = '<p style="' . $this->styles . '" class="single-post-message">';
	    		$post_message .= get_post_meta( get_the_ID(), 'single_post_message', true );
	    	$post_message .= '</p><!-- /.single-post-message -->';
	    	
	    	$content = $post_message . $content;
	    	
    	} // end if 
    	
    	return $content;
    	
	} // end prepend_post_message
  
} // end class

new Single_Post_Message();
?>