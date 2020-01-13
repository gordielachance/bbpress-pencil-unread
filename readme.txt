=== bbPress Pencil Unread ===
Contributors: grosbouff
Tags: bbPress,unread,mark as read,new,topics,forums,Buddypress
Donate link: http://bit.ly/gbreant
Requires at least: 3
Tested up to: 5.3.2
Requires PHP: 5.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

bbPress Pencil Unread display which bbPress forums/topics have already been read by the user.

== Description ==

bbPress Pencil Unread display which bbPress forums/topics have already been read by the logged user; and adds classes to forums/topics so you can customize your theme easily.
Compatible with BuddyPress Groups Forums feature.

*   For **forums**, it checks if the user has read all topics since last visit.
*   For **topics**, it checks if the user opened the topic since it was last active.
*   *Mark as read* (optional) mark all topics of a forum as read.
*   *Bookmarks* : (optional) adds a link after the topics titles; that goes directly to the last read reply of a topic.
*   Option to set as read topics that where created before the user's registration

= Demo =

We don't have a running demo anymore.  If you use this plugin and would like to be featured here, please [contact us](https://github.com/gordielachance/bbpress-pencil-unread/issues/5)

= Donate =

Donations are needed to help maintain this plugin.  Please consider [supporting us](http://bit.ly/gbreant).
This would be very appreciated — Thanks !

= Contributors =

Contributors [are listed here](https://github.com/gordielachance/bbpress-pencil-unread/contributors)

= Bugs/Development =

For feature request and bug reports, please use the [Github Issues Tracker](https://github.com/gordielachance/bbpress-pencil-unread/issues).

If you are a plugin developer, [we would like to hear from you](https://github.com/gordielachance/bbpress-pencil-unread). Any contribution would be very welcome.

== Installation ==

1. Upload the plugin to your blog and Activate it.

== Frequently Asked Questions ==

= It doesn't work / CSS styling is bad ! =

Styling has been setup for the bbPress default theme.  
If it doesn't work for you, please try to check/override our CSS styles (bbppu.css)

= How can I filter topics to display only the unread or read ones ? =
Just add the **bbppu** arg to your [Wordpress queries](https://codex.wordpress.org/Class_Reference/WP_Query).  You can set it either to *read* or *unread*.
Of course, this works for the current logged user and will be skipped if the visitor is not logged.

Example :

`<?php
$last_unread_topics_query_args = array(
  'post_type'       => bbp_topic_post_type(), //or 'topic'
  'posts_per_page'  => 5,
  'bbppu'       => 'unread' //only unread ones
);

$last_unread_topics_query = new WP_Query( $last_unread_topics_query_args );
?>`


= How does it work? =

*bbPress Pencil Unread* handles differently the way forums & topics are set as read.

*  For **topics**, a post meta *bbppu_read_by* is attached to the topic each time a someone visits it; the meta value is the user ID.  When a new reply is added, all those metas are deleted.

*  For **forums**, we compare the total count of topics with the total count of read topics for the current user.  If it does not match, the forum is considered as unread.

*  Marking a forum (*Mark all as read*) adds an entry with the forum ID and timestamp in *bbppu_marked_forums* (usermeta).  When determining if a topic has been read, we check if the topic's forum (or ancestors) has a mark more recent than the topic time.

* Marking a forum will only set the topics from this forum as read.  If there is **super sticky topics** displayed and that they belong to other forums, they will not be marked as read.

= How can I use those functions outside of the plugin ? =

Have a look at the file /bbppu-template.php, which contains functions you could need.

= How can I see the plugin's log ? =

The plugin will generate various notices and informations in the debug.log file, if [debugging is enabled](https://codex.wordpress.org/Debugging_in_WordPress).

== Screenshots ==

1. Style of the read / non-read forums.  The flag icon is a link to reach the last read reply.
2. Options page

== Localization ==

If it hasn't been done already, you can translate the plugin and send me the translation.  I recommand [Loco Translate](https://fr.wordpress.org/plugins/loco-translate/) to work on your translations within Wordpress.

== Changelog ==

= 1.3.0 =
* Try to optimize queries that count forum topics in has_user_read_all_forum_topics():
* 'no_found_rows' => true (see https://wpartisan.me/tutorials/wordpress-database-queries-speed-sql_calc_found_rows); so use count() instead of found->posts
* 'update_post_term_cache' => false

= 1.2.9 =
* when comparing the topics read, only fetch IDs instead of full post
* better debug.log report

= 1.2.7 =
* Added the 'bookmark' option, which adds (by default) a link after topics titles to go directly to the last read reply of that topic.
* Forums marks is now an option

= 1.2.6 =
* Added meta query in has_user_read_all_forum_topics() to ignore posts below timestamp generated in get_skip_timestamp()
* New function get_skip_timestamp()

= 1.2.4 =
* Use utf8 encoding when running $dom->loadHTML() to avoid problems with foreign languages (http://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly)
= 1.2.3 =
* No faking anymore !  Now the plugin **really** checks if a forum has its topics all read; while before, it was checking if the forum had been **opened**.
* Allow to filter queries to get topics by read/unread status (see FAQ)
* fixed loadHTML() error (https://wordpress.org/support/topic/just-upgraded-to-v-1-2-errors/#post-8169136)
* Arabic translation (thanks to Mohammad Sy)
* has_user_read_all_forum_topics() : store the results in a short transient (5s) to avoid querying several times the same stuff.
* deleted 'bbppu_forums_visits' usermetas and related functions (+ upgrade function)
* topic_readby_metaname is now multiple (+ upgrade function) : do not store array of user IDs in a single meta, but store multiple metas with single user ID each time

= 1.2.2 =
* Do not show 'Mark as read' link if no activity since last marked.
* fixed localization + french translation
* stylesheet : RTL support
* code cleanup
* jQuery : when marking a forum as read, give the 'bbppu-read' class only to the topics of that forum (super sticky topics could be from another forum so they should remain unread)

= 1.2.1 =
* bug fixes : https://wordpress.org/support/topic/just-upgraded-to-v-1-2-errors/

= 1.2 =
* SCSS
* options page
* option to choose if items created before first user's visit should be marked as read ('test_registration_time') - https://wordpress.org/support/topic/old-topics-as-unread/
* includes fontAwesome (loading icon : glyph instead of image)
* Improved function get_user_mark_as_read_link()
* Improved function process_mark_as_read()
* improved ajax and nonces checks
* supports forums hierarchy

= 1.1.1 =
* Removed first forum visit stuff.  Remove old metas.  Now check users registration time.
* Lots of code cleanup
* Improved has_user_read() function

= 1.1.0 =
* Improved marking as read - among others, checks if a parent forum has been marked.
* Merged multiple "bbppu_marked_forum_XX" user meta keys into "bbppu_marked_forums" + upgrade function for older versions of the plugin.
* New debug_log() function
* Now handles forum categories
* Merged functions 'has_user_read_forum' and 'has_user_read_topic' to 'has_user_read'
* Merged functions 'forum_status_class' and 'topic_status_class' to 'post_status_class'
* Removed bbP_Pencil_Unread variable 'prefix'

= 1.0.9 =
* Undefined index bug fix (http://wordpress.org/support/topic/php-notice-for-mark_as_read_single_forum_link?replies=3#post-4842854)

= 1.0.7 =
* Fixed minor bug (http://wordpress.org/support/topic/php-notice-for-mark_as_read_single_forum_link)

= 1.0.6 =
* Fixed minor bugs from 1.0.5

= 1.0.5 =
* Compatible with BuddyPress Groups Forums !
* Backend integration (new_topic_backend,new_reply_backend)
* Better firing sequence
* Fixed styles for "mark as read" link

= 1.0.4 =
* Now saving the user's first visit (user meta key "bbppu_first_visit") to define older content as "read".
* In 'setup_actions()', replaced wordpress hooks by bbpress hooks (to avoid plugin to crash while bbPress is not enabled)

= 1.0.3 =
* Added link "mark as read" for forums
* Added filter 'bbppu_user_has_read_forum' on has_user_read_forum() and 'bbppu_user_has_read_topic' on has_user_read_topic()

= 1.0.2 =
* Timezone bug fix (thanks to Ruben!)

= 1.0.1 =
* If a forum was set as "read" when a user posts a new topic or reply, keep its status to read after the new post has been saved (see function related to var $forum_was_read_before_new_post)
* Store plugin version
* Cleaned up the code

= 1.0.0 =
* First release