=== bbPress Pencil Unread ===
Contributors: grosbouff 
Tags: bbpress,unread,topics,forums,buddypress
Requires at least: Wordpress 3, bbPress 2
Tested up to: Wordpress 3.7.1, bbPress 2.4.1
Stable tag: trunk
License:GPLv2 or later
Donate link:http://bit.ly/gbreant

bbPress Pencil Unread display which bbPress forums/topics have already been read by the user.

== Description ==

bbPress Pencil Unread display which bbPress forums/topics have already been read by the logged user; and adds classes to forums/topics so you can customize your theme easily.  
  
*   For **forums**, it checks if the user has visited the forum since it was last active.
*   For **topics**, it checks if the user opened the topic since it was last active.

It also allows to set all topics of a forum as read.

= Contributors =
[Contributors are listed
here](https://github.com/gordielachance/bbpress-pencil-unread/contributors)

= Demo =
bbPress Pencil Unread is installed on [our forums](http://sandbox.pencil2d.org/forums).

= Notes =

For feature request and bug reports, [please use the
forums](http://wordpress.org/support/plugin/bbpress-pencil-unread#postform).

If you are a plugin developer, [we would like to hear from
you](https://github.com/gordielachance/bbpress-pencil-unread). Any contribution would be
very welcome.

== Installation ==

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page.

== Frequently Asked Questions ==

= How does it work? =

*bbPress Pencil Unread* handles differently the way forums & topics are set as read.

*   For **topics**, the ID of each visitor having read the topic is is stored in *bbppu_read_by* (posts metas table) when the topic is opened.  When a new reply is added, the IDs of the users having already read the topic are deleted.
*   For **forums**, the time of each forum's last access by the user is stored in *bbppu_forums_visits* (users metas table) on forum visits, and compared to the forum last activity time. This means a forum will be set as "read" if the user has visited the forum page, and even if some topics inside have not been read (but they will remain listed as non read topics when displaying the forum).
  
It's working that way to avoid having too much database calls / data stored.

*   To avoid that a forum would be set set as 'unread' after a user **posts a new topic or reply**; we check the forum status (if it was read or not) before posting the topic or reply.  If the forum was set to read, we'll keep that !

= How can I use those functions outside of the plugin ? =

Have a look at the file /bbppu-template.php, which contains functions you could need.

== Screenshots ==
1. Style of the read / non-read forums

== Changelog ==
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