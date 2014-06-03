<?php

/*
  Plugin Name: One Click Comments
  Plugin URI: http://www.asafcohen.net
  Description: Allow approve/trash/spam comments directly from the email.
  Author: Asaf Cohen
  Author URI: http://www.asafcohen.net
  Version: 1.0
 */

/**
 * Override plugable funciton `wp_notify_postauthor`
 * Notify an author (and/or others) of a comment/trackback/pingback on a post.
 *
 * @since 1.0.0
 *
 * @param int $comment_id Comment ID
 * @param string $deprecated Not used
 * @return bool True on completion. False if no email addresses were specified.
 */
if ( !function_exists( 'wp_notify_postauthor' ) ) :

    function wp_notify_postauthor( $comment_id , $deprecated = null ) {
        if ( null !== $deprecated ) {
            _deprecated_argument( __FUNCTION__ , '3.8' );
        }

        $comment = get_comment( $comment_id );
        if ( empty( $comment ) )
            return false;

        $post = get_post( $comment->comment_post_ID );
        $author = get_userdata( $post->post_author );

        // Who to notify? By default, just the post author, but others can be added.
        $emails = array( $author->user_email );

        /**
         * Filter the list of emails to receive a comment notification.
         *
         * Normally just post authors are notified of emails.
         * This filter lets you add others.
         *
         * @since 3.7.0
         *
         * @param array $emails     Array of email addresses to receive a comment notification.
         * @param int   $comment_id The comment ID.
         */
        $emails = apply_filters( 'comment_notification_recipients' , $emails , $comment_id );
        $emails = array_filter( $emails );

        // If there are no addresses to send the comment to, bail.
        if ( !count( $emails ) ) {
            return false;
        }

        // Facilitate unsetting below without knowing the keys.
        $emails = array_flip( $emails );

        /**
         * Filter whether to notify comment authors of their comments on their own posts.
         *
         * By default, comment authors don't get notified of their comments
         * on their own post. This lets you override that.
         *
         * @since 3.8.0
         *
         * @param bool $notify     Whether to notify the post author of their own comment. Default false.
         * @param int  $comment_id The comment ID.
         */
        $notify_author = apply_filters( 'comment_notification_notify_author' , false , $comment_id );

        // The comment was left by the author
        if ( !$notify_author && $comment->user_id == $post->post_author ) {
            unset( $emails[ $author->user_email ] );
        }

        // The author moderated a comment on their own post
        if ( !$notify_author && $post->post_author == get_current_user_id() ) {
            unset( $emails[ $author->user_email ] );
        }

        // The post author is no longer a member of the blog
        if ( !$notify_author && !user_can( $post->post_author , 'read_post' , $post->ID ) ) {
            unset( $emails[ $author->user_email ] );
        }

        // If there's no email to send the comment to, bail, otherwise flip array back around for use below
        if ( !count( $emails ) ) {
            return false;
        }
        else {
            $emails = array_flip( $emails );
        }

        $comment_author_domain = @gethostbyaddr( $comment->comment_author_IP );

        // The blogname option is escaped with esc_html on the way into the database in sanitize_option
        // we want to reverse this for the plain text arena of emails.
        $blogname = wp_specialchars_decode( get_option( 'blogname' ) , ENT_QUOTES );

        switch ( $comment->comment_type ) {
            case 'trackback':
                $notify_message = sprintf( __( 'New trackback on your post "%s"' ) , $post->post_title ) . "\r\n";
                /* translators: 1: website name, 2: author IP, 3: author domain */
                $notify_message .= sprintf( __( 'Website: %1$s (IP: %2$s , %3$s)' ) , $comment->comment_author , $comment->comment_author_IP , $comment_author_domain ) . "\r\n";
                $notify_message .= sprintf( __( 'URL    : %s' ) , $comment->comment_author_url ) . "\r\n";
                $notify_message .= __( 'Excerpt: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
                $notify_message .= __( 'You can see all trackbacks on this post here: ' ) . "\r\n";
                /* translators: 1: blog name, 2: post title */
                $subject = sprintf( __( '[%1$s] Trackback: "%2$s"' ) , $blogname , $post->post_title );
                break;
            case 'pingback':
                $notify_message = sprintf( __( 'New pingback on your post "%s"' ) , $post->post_title ) . "\r\n";
                /* translators: 1: comment author, 2: author IP, 3: author domain */
                $notify_message .= sprintf( __( 'Website: %1$s (IP: %2$s , %3$s)' ) , $comment->comment_author , $comment->comment_author_IP , $comment_author_domain ) . "\r\n";
                $notify_message .= sprintf( __( 'URL    : %s' ) , $comment->comment_author_url ) . "\r\n";
                $notify_message .= __( 'Excerpt: ' ) . "\r\n" . sprintf( '[...] %s [...]' , $comment->comment_content ) . "\r\n\r\n";
                $notify_message .= __( 'You can see all pingbacks on this post here: ' ) . "\r\n";
                /* translators: 1: blog name, 2: post title */
                $subject = sprintf( __( '[%1$s] Pingback: "%2$s"' ) , $blogname , $post->post_title );
                break;
            default: // Comments
                $notify_message = sprintf( __( 'New comment on your post "%s"' ) , $post->post_title ) . "\r\n";
                /* translators: 1: comment author, 2: author IP, 3: author domain */
                $notify_message .= sprintf( __( 'Author : %1$s (IP: %2$s , %3$s)' ) , $comment->comment_author , $comment->comment_author_IP , $comment_author_domain ) . "\r\n";
                $notify_message .= sprintf( __( 'E-mail : %s' ) , $comment->comment_author_email ) . "\r\n";
                $notify_message .= sprintf( __( 'URL    : %s' ) , $comment->comment_author_url ) . "\r\n";
                $notify_message .= sprintf( __( 'Whois  : http://whois.arin.net/rest/ip/%s' ) , $comment->comment_author_IP ) . "\r\n";
                $notify_message .= __( 'Comment: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
                $notify_message .= __( 'You can see all comments on this post here: ' ) . "\r\n";
                /* translators: 1: blog name, 2: post title */
                $subject = sprintf( __( '[%1$s] Comment: "%2$s"' ) , $blogname , $post->post_title );
                break;
        }
        $notify_message .= get_permalink( $comment->comment_post_ID ) . "#comments\r\n\r\n";
        $notify_message .= sprintf( __( 'Permalink: %s' ) , get_permalink( $comment->comment_post_ID ) . '#comment-' . $comment_id ) . "\r\n";

        if ( user_can( $post->post_author , 'edit_comment' , $comment_id ) ) {
            // Give the user the AJAX link with a valid security token for each action.
            if ( EMPTY_TRASH_DAYS ) {
                $notify_message .= sprintf( __( 'Trash it: %s' ) , admin_url( "admin-ajax.php?action=occ_actions&action_type=trash&comment_id=$comment_id&token=" . occ_create_token( $comment_id , "trash" ) ) ) . "\r\n";
            }
            else {
                $notify_message .= sprintf( __( 'Delete it: %s' ) , admin_url( "admin-ajax.php?action=occ_actions&action_type=delete&comment_id=$comment_id&token=" . occ_create_token( $comment_id , "delete" ) ) ) . "\r\n";
            }
            $notify_message .= sprintf( __( 'Spam it: %s' ) , admin_url( "admin-ajax.php?action=occ_actions&action_type=spam&comment_id=$comment_id&token=" . occ_create_token( $comment_id , "spam" ) ) ) . "\r\n";
        }

        $wp_email = 'wordpress@' . preg_replace( '#^www\.#' , '' , strtolower( $_SERVER[ 'SERVER_NAME' ] ) );

        if ( '' == $comment->comment_author ) {
            $from = "From: \"$blogname\" <$wp_email>";
            if ( '' != $comment->comment_author_email )
                $reply_to = "Reply-To: $comment->comment_author_email";
        } else {
            $from = "From: \"$comment->comment_author\" <$wp_email>";
            if ( '' != $comment->comment_author_email )
                $reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
        }

        $message_headers = "$from\n"
                . "Content-Type: text/plain; charset=\"" . get_option( 'blog_charset' ) . "\"\n";

        if ( isset( $reply_to ) )
            $message_headers .= $reply_to . "\n";

        $notify_message = apply_filters( 'comment_notification_text' , $notify_message , $comment_id );
        $subject = apply_filters( 'comment_notification_subject' , $subject , $comment_id );
        $message_headers = apply_filters( 'comment_notification_headers' , $message_headers , $comment_id );

        foreach ( $emails as $email ) {
            @wp_mail( $email , $subject , $notify_message , $message_headers );
        }

        return true;
    }

endif;


/**
 * Override plugable funciton `wp_notify_moderator`
 * Notifies the moderator of the blog about a new comment that is awaiting approval.
 *
 * @since 1.0
 * @uses $wpdb
 *
 * @param int $comment_id Comment ID
 * @return bool Always returns true
 */
if ( !function_exists( 'wp_notify_moderator' ) ) :

    function wp_notify_moderator( $comment_id ) {
        global $wpdb;

        if ( 0 == get_option( 'moderation_notify' ) )
            return true;

        $comment = get_comment( $comment_id );
        $post = get_post( $comment->comment_post_ID );
        $user = get_userdata( $post->post_author );
        // Send to the administration and to the post author if the author can modify the comment.
        $emails = array( get_option( 'admin_email' ) );
        if ( user_can( $user->ID , 'edit_comment' , $comment_id ) && !empty( $user->user_email ) ) {
            if ( 0 !== strcasecmp( $user->user_email , get_option( 'admin_email' ) ) )
                $emails[] = $user->user_email;
        }

        $comment_author_domain = @gethostbyaddr( $comment->comment_author_IP );
        $comments_waiting = $wpdb->get_var( "SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'" );

        // The blogname option is escaped with esc_html on the way into the database in sanitize_option
        // we want to reverse this for the plain text arena of emails.
        $blogname = wp_specialchars_decode( get_option( 'blogname' ) , ENT_QUOTES );

        switch ( $comment->comment_type ) {
            case 'trackback':
                $notify_message = sprintf( __( 'A new trackback on the post "%s" is waiting for your approval' ) , $post->post_title ) . "\r\n";
                $notify_message .= get_permalink( $comment->comment_post_ID ) . "\r\n\r\n";
                $notify_message .= sprintf( __( 'Website : %1$s (IP: %2$s , %3$s)' ) , $comment->comment_author , $comment->comment_author_IP , $comment_author_domain ) . "\r\n";
                $notify_message .= sprintf( __( 'URL    : %s' ) , $comment->comment_author_url ) . "\r\n";
                $notify_message .= __( 'Trackback excerpt: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
                break;
            case 'pingback':
                $notify_message = sprintf( __( 'A new pingback on the post "%s" is waiting for your approval' ) , $post->post_title ) . "\r\n";
                $notify_message .= get_permalink( $comment->comment_post_ID ) . "\r\n\r\n";
                $notify_message .= sprintf( __( 'Website : %1$s (IP: %2$s , %3$s)' ) , $comment->comment_author , $comment->comment_author_IP , $comment_author_domain ) . "\r\n";
                $notify_message .= sprintf( __( 'URL    : %s' ) , $comment->comment_author_url ) . "\r\n";
                $notify_message .= __( 'Pingback excerpt: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
                break;
            default: // Comments
                $notify_message = sprintf( __( 'A new comment on the post "%s" is waiting for your approval' ) , $post->post_title ) . "\r\n";
                $notify_message .= get_permalink( $comment->comment_post_ID ) . "\r\n\r\n";
                $notify_message .= sprintf( __( 'Author : %1$s (IP: %2$s , %3$s)' ) , $comment->comment_author , $comment->comment_author_IP , $comment_author_domain ) . "\r\n";
                $notify_message .= sprintf( __( 'E-mail : %s' ) , $comment->comment_author_email ) . "\r\n";
                $notify_message .= sprintf( __( 'URL    : %s' ) , $comment->comment_author_url ) . "\r\n";
                $notify_message .= sprintf( __( 'Whois  : http://whois.arin.net/rest/ip/%s' ) , $comment->comment_author_IP ) . "\r\n";
                $notify_message .= __( 'Comment: ' ) . "\r\n" . $comment->comment_content . "\r\n\r\n";
                break;
        }
        // Give the user the AJAX link with a valid security token for each action.
        $notify_message .= sprintf( __( 'Approve it: %s' ) , admin_url( "admin-ajax.php?action=occ_actions&action_type=approve&comment_id=$comment_id&token=" . occ_create_token( $comment_id , "approve" ) ) ) . "\r\n";
        if ( EMPTY_TRASH_DAYS ) {
            $notify_message .= sprintf( __( 'Trash it: %s' ) , admin_url( "admin-ajax.php?action=occ_actions&action_type=trash&comment_id=$comment_id&token=" . occ_create_token( $comment_id , "trash" ) ) ) . "\r\n";
        }
        else {
            $notify_message .= sprintf( __( 'Delete it: %s' ) , admin_url( "admin-ajax.php?action=occ_actions&action_type=delete&comment_id=$comment_id&token=" . occ_create_token( $comment_id , "delete" ) ) ) . "\r\n";
        }
        $notify_message .= sprintf( __( 'Spam it: %s' ) , admin_url( "admin-ajax.php?action=occ_actions&action_type=spam&comment_id=$comment_id&token=" . occ_create_token( $comment_id , "spam" ) ) ) . "\r\n";

        $notify_message .= sprintf( _n( 'Currently %s comment is waiting for approval. Please visit the moderation panel:' , 'Currently %s comments are waiting for approval. Please visit the moderation panel:' , $comments_waiting ) , number_format_i18n( $comments_waiting ) ) . "\r\n";
        $notify_message .= admin_url( "edit-comments.php?comment_status=moderated" ) . "\r\n";

        $subject = sprintf( __( '[%1$s] Please moderate: "%2$s"' ) , $blogname , $post->post_title );
        $message_headers = '';

        $emails = apply_filters( 'comment_moderation_recipients' , $emails , $comment_id );
        $notify_message = apply_filters( 'comment_moderation_text' , $notify_message , $comment_id );
        $subject = apply_filters( 'comment_moderation_subject' , $subject , $comment_id );
        $message_headers = apply_filters( 'comment_moderation_headers' , $message_headers , $comment_id );

        foreach ( $emails as $email ) {
            @wp_mail( $email , $subject , $notify_message , $message_headers );
        }

        return true;
    }

endif;


/*
 * Add AJAX action
 */
add_action( "wp_ajax_nopriv_occ_actions" , "occ_actions" );
add_action( "wp_ajax_occ_actions" , "occ_actions" );

/**
 * Excute action by request
 *
 */
function occ_actions() {
    // Validate security token
    if ( occ_create_token( $_REQUEST[ 'comment_id' ] , $_REQUEST[ 'action_type' ] ) == $_REQUEST[ 'token' ] ) {
        // Close window script
        $close_window_script = '<html><head><script type="javascript">setTimeout(function(){window.close();},3000);</script></head><body><h1>Comment was successfully ' . $_REQUEST[ 'action_type' ] . '.</h1></body></html>';
        switch ( $_REQUEST[ 'action_type' ] ) {
            case "approve":
                // Change comment status to approve
                if ( wp_set_comment_status( intval( $_REQUEST[ 'comment_id' ] ) , "approve" ) ) {
                    die( $close_window_script );
                }
                break;
            case "delete":
                // Delete comment
                if ( wp_delete_comment( intval( $_REQUEST[ 'comment_id' ] ) ) ) {
                    die( $close_window_script );
                }
                break;
            case "trash":
                // Move comment to trash
                if ( wp_trash_comment( intval( $_REQUEST[ 'comment_id' ] ) ) ) {
                    die( $close_window_script );
                }
                break;

            case "spam":
                // Mark comment as spam
                if ( wp_spam_comment( intval( $_REQUEST[ 'comment_id' ] ) ) ) {
                    die( $close_window_script );
                }
                break;
        }
    }
    else {
        // Security token not currect
        wp_die( __( "Security token is not valid." ) );
    }
    // Error, can be trigged if action type/comment id not set or the plugin failed to excute to action.
    wp_die( "ERROR" );
}

/**
 * Generate unique token for security purpose
 *
 * @param int $comment_id Comment ID
 * @param string $action_type Action Type
 * @return String, 40 digits long
 */
function occ_create_token( $comment_id , $action_type ) {
    return sha1( $comment_id . $action_type . NONCE_SALT );
}
