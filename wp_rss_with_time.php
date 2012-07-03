<?php
/*
Plugin Name: WP Rss With Time
Plugin URI: https://github.com/mittmedia/i_write_about
Description: Adds user meta with users topic of choice.
Version: 1.0.0
Author: Fredrik Sundström
Author URI: https://github.com/fredriksundstrom
License: MIT
*/

/*
Copyright (c) 2012 Fredrik Sundström

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
*/

add_action( 'widgets_init', 'myplugin_register_widgets' );
function myplugin_register_widgets() {
  register_widget( 'WP_Rss_With_Time' );
}

class WP_Rss_With_Time extends WP_Widget {

  function __construct() {
    $widget_ops = array( 'description' => __('Entries from any RSS or Atom feed') );
    $control_ops = array( 'width' => 400, 'height' => 200 );
    parent::__construct( 'rss', __('RSS 2'), $widget_ops, $control_ops );
  }

  function widget($args, $instance) {

    if ( isset($instance['error']) && $instance['error'] )
      return;

    extract($args, EXTR_SKIP);

    $url = ! empty( $instance['url'] ) ? $instance['url'] : '';
    while ( stristr($url, 'http') != $url )
      $url = substr($url, 1);

    if ( empty($url) )
      return;

    // self-url destruction sequence
    if ( in_array( untrailingslashit( $url ), array( site_url(), home_url() ) ) )
      return;

    $rss = fetch_feed($url);
    $title = $instance['title'];
    $desc = '';
    $link = '';

    if ( ! is_wp_error($rss) ) {
      $desc = esc_attr(strip_tags(@html_entity_decode($rss->get_description(), ENT_QUOTES, get_option('blog_charset'))));
      if ( empty($title) )
        $title = esc_html(strip_tags($rss->get_title()));
      $link = esc_url(strip_tags($rss->get_permalink()));
      while ( stristr($link, 'http') != $link )
        $link = substr($link, 1);
    }

    if ( empty($title) )
      $title = empty($desc) ? __('Unknown Feed') : $desc;

    $title = apply_filters('widget_title', $title, $instance, $this->id_base);
    $url = esc_url(strip_tags($url));
    $icon = includes_url('images/rss.png');
    if ( $title )
      $title = "<a class='rsswidget' href='$url' title='" . esc_attr__( 'Syndicate this content' ) ."'><img style='border:0' width='14' height='14' src='$icon' alt='RSS' /></a> <a class='rsswidget' href='$link' title='$desc'>$title</a>";

    echo $before_widget;
    if ( $title )
      echo $before_title . $title . $after_title;
    wp_rss_with_time_rss_output( $rss, $instance );
    echo $after_widget;

    if ( ! is_wp_error($rss) )
      $rss->__destruct();
    unset($rss);
  }

  function update($new_instance, $old_instance) {
    $testurl = ( isset( $new_instance['url'] ) && ( !isset( $old_instance['url'] ) || ( $new_instance['url'] != $old_instance['url'] ) ) );
    return wp_widget_rss_process( $new_instance, $testurl );
  }

  function form($instance) {

    if ( empty($instance) )
      $instance = array( 'title' => '', 'url' => '', 'items' => 10, 'error' => false, 'show_summary' => 0, 'show_author' => 0, 'show_date' => 0 );
    $instance['number'] = $this->number;

    wp_widget_rss_form( $instance );
  }
}

function wp_rss_with_time_rss_output( $rss, $args = array() ) {
  if ( is_string( $rss ) ) {
    $rss = fetch_feed($rss);
  } elseif ( is_array($rss) && isset($rss['url']) ) {
    $args = $rss;
    $rss = fetch_feed($rss['url']);
  } elseif ( !is_object($rss) ) {
    return;
  }

  if ( is_wp_error($rss) ) {
    if ( is_admin() || current_user_can('manage_options') )
      echo '<p>' . sprintf( __('<strong>RSS Error</strong>: %s'), $rss->get_error_message() ) . '</p>';
    return;
  }

  $default_args = array( 'show_author' => 0, 'show_date' => 0, 'show_summary' => 0 );
  $args = wp_parse_args( $args, $default_args );
  extract( $args, EXTR_SKIP );

  $items = (int) $items;
  if ( $items < 1 || 20 < $items )
    $items = 10;
  $show_summary  = (int) $show_summary;
  $show_author   = (int) $show_author;
  $show_date     = (int) $show_date;

  if ( !$rss->get_item_quantity() ) {
    echo '<ul><li>' . __( 'An error has occurred; the feed is probably down. Try again later.' ) . '</li></ul>';
    $rss->__destruct();
    unset($rss);
    return;
  }

  echo '<ul>';
  foreach ( $rss->get_items(0, $items) as $item ) {
    $link = $item->get_link();
    while ( stristr($link, 'http') != $link )
      $link = substr($link, 1);
    $link = esc_url(strip_tags($link));
    $title = esc_attr(strip_tags($item->get_title()));
    if ( empty($title) )
      $title = __('Untitled');

    $desc = str_replace( array("\n", "\r"), ' ', esc_attr( strip_tags( @html_entity_decode( $item->get_description(), ENT_QUOTES, get_option('blog_charset') ) ) ) );
    $desc = wp_html_excerpt( $desc, 360 );

    // Append ellipsis. Change existing [...] to [&hellip;].
    if ( '[...]' == substr( $desc, -5 ) )
      $desc = substr( $desc, 0, -5 ) . '[&hellip;]';
    elseif ( '[&hellip;]' != substr( $desc, -10 ) )
      $desc .= ' [&hellip;]';

    $desc = esc_html( $desc );

    if ( $show_summary ) {
      $summary = "<div class='rssSummary'>$desc</div>";
    } else {
      $summary = '';
    }

    $date = '';
    if ( $show_date ) {
      $date = $item->get_date( 'U' );

      if ( $date ) {
        $date = ' <span class="rss-date">' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) . '</span>';
      }
    }

    $author = '';
    if ( $show_author ) {
      $author = $item->get_author();
      if ( is_object($author) ) {
        $author = $author->get_name();
        $author = ' <cite>' . esc_html( strip_tags( $author ) ) . '</cite>';
      }
    }

    if ( $link == '' ) {
      echo "<li>$title{$date}{$summary}{$author}</li>";
    } else {
      echo "<li><a class='rsswidget' href='$link' title='$desc'>$title</a>{$date}{$summary}{$author}</li>";
    }
  }
  echo '</ul>';
  $rss->__destruct();
  unset($rss);
}
