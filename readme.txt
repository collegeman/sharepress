=== Plugin Name ===
Contributors: aaroncollegeman
Donate link: http://aaroncollegeman.com/sharepress
Tags: facebook, twitter, social, like, posts, page
Requires at least: 2.9
Tested up to: 3.3
Stable tag: 2.0.23

Share the content you write in WordPress with your Facebook Fans and Twitter Followers, simply and reliably.

== Description ==

SharePress is a WordPress plugin that helps you communicate with your [tribes](http://sethgodin.typepad.com/seths_blog/2008/01/tribal-manageme.html) on Facebook and Twitter by automatically publishing your WordPress posts the moment they become live on your site.

With SharePress you'll be able to

* Publish your WordPress posts to any/all of the Facebook pages you manage
* Publish to your Facebook wall
* Publish to your Twitter followers **NEW!**
* Customize each Facebook status message
* Control the image Facebook uses just by setting the post's featured image
* Customize Twitter hashtag
* Publish while you sleep: social messaging is published when posts go live
* Schedule reposts of your content: keep traffic flowing to your site day and night

Want to read what our customers have said about SharePress? [Check this out](http://aaroncollegeman.com/sharepress?utm_source=wordpress.org&utm_medium=app-store&utm_campaign=testimonials).

== Installation ==

Want to try SharePress for free?

1. Get the plugin. Activate the plugin.

2. Create a Facebook application.

3. Go to Settings / SharePress, and run setup.

Need support? [Go here](http://aaroncollegeman.com/sharepress?utm_source=wordpress.org&utm_medium=app-store&utm_campaign=get-support).

== Frequently Asked Questions ==

= How do I post to the wall of my Facebook page? =

You need the pro version. All you have to do is [buy a key](http://aaroncollegeman.com/sharepress?utm_source=wordpress.org&utm_medium=app-store&utm_campaign=post-to-page).

== Changelog ==

= 2.0.23 =
* Fixed: Proper detection of Featured Image defaults in XML-RPC posts
* Change: Dismiss inline errors as user makes corrections to meta data selections (e.g., if we say pick a target, and they do, immediately hide the error)

= 2.0.22 =
* Fixed: Regression: not posting in XML-RPC requests

= 2.0.21 =
* Fully compatible with WordPress 3.3 "Sonny"
* Change: "Let Facebook choose" mode for post image has been replaced with "Use the first image in the post," which is a much better default
* Change: Don't display the setup warning everywhere
* Added: Now you can toggle the post link that appears at the end of Facebook messages
* Added: Filter "sharepress_get_permalink" for influencing the permalink SharePress uses in posts to Facebook and Twitter, and for the og:url field
* Fixed: Scheduled posts were posted to Facebook even when set not to be
* Fixed: Don't display error for Featured Image when post is being submitted for review by a contributor

= 2.0.20 =
* Fixed: (again) Taking another shot at the shortcode-in-og:description-problem
* Fixed: Usability issues with the new Featured Image confirmation prompt
* Added: New global options for controlling from where the picture used by Facebook is sourced (featured image, global, or essentially-random)
* Change: Turned off the pinger. I wasn't using the data, and it upset some of my licensed customers. This will come back later in some more manageable form

= 2.0.19 =
* Fixed: The sharepress-mu.php file was all kinds of broken. Now works for the purpose of setting your license key, App Id, and App Secret in one place. Also, it's no longer part of the distribution. Sent only to people who buy the license.
* Fixed: Shortcodes appearing in og:description
* Fixed: Google+Blog wasn't posting to Facebook
* Fixed: Missing campaign tracking on some links in free version of the plugin
* Fixed: Default image size now 150x150
* Added: JS confirmation when no Featured Image is specified
* Added: Optional Twitter hash tag, customizeable for each post

= 2.0.18 =
* Fixed: If a Facebook connection error occurs on the Edit Post screen, the error message is hidden in the collapsed "Advanced" section of the meta box
* Fixed: Sometimes WP fires SharePress' one-minute cron job more than once a minute, resulting in multiple posts to Facebook
* Fixed: OG meta tags not turned on by default
* Fixed: A bunch of usability issues in the Settings screens
* Added: You can now elect to have your Posts shared on Twitter - no messaging customization yet: just post title and permalink

= 2.0.17 =
* Change: The Open Graph tags SharePress is allowed to insert can now be independently turned on and off, instead of in bulky groups
* Added: fb:app_id can be inserted automatically 
* Added: og:description can be inserted automatically

= 2.0.16 =
* Added: Log file viewer

= 2.0.15 =
* Fixed: Upgraded to Facebook PHP SDK 3.1.1
* Fixed: Now using oAuth 2.0 for Facebook login
* Fixed: No longer using JavaScript SDK in the admin, so domain name restrictions no longer matter (i.e., WordPress MU)
* Fixed: No longer dependent upon cURL, instead using WP_Http (thanks to [Curtiss Grymala](http://www.facebook.com/cgrymala))

= 2.0.14 =
* Fixed: Some minor issues related to calling array_merge without a defined array

= 2.0.13 =
* Fixed: The bug introduced by 2.0.12 - everything was considered RPC because I forgot to treat the constant like a constant...

= 2.0.12 =
* Added: Support for posting to Facebook with SharePress via XML-RPC. You can't configure what the Facebook post will say -- it's all defaults. But it didn't work at all before. This is progress.

= 2.0.11 =
* Fixed: I wasn't actually reading the user's per-post configuration when determining what image to identify in the og:image tag

= 2.0.10 =
* Fixed: Stop escaping unicode characters in og: meta data
* Added: You can now indicate that SharePress should only insert the og:image meta tag; this is useful for installations that already have plugins inserting the meta data, but not the og:image tag

= 2.0.9 =
* Fixed: Choice "No" in SharePress meta box was not being saved
* Fixed: Took some steps to reduce issues with license keys
* Added: a lot more logging statements, to help debug some problems with scheduled posts

= 2.0.8 =
* Change: Facebook changed the URL for the linter, so I've updated SharePress to use the new URL

= 2.0.7 =
* Change: Made it possible to reset the Facebook session from within the text of critical error messages

= 2.0.6 =
* Fixed: For sites that don't use a Page for the front door, the og:url meta was using the first permalink of the first post from the loop. This is wrong, it should be using the siteurl on the home page. This is now fixed.
* Fixed: Default piture was not being used on Posts that didn't set a Featured Image, but weren't set to allow Facebook to pick a picture

= 2.0.5 =
* Change: Renamed "Sharepress" to "SharePress"
* Added: Tutorial video for setting up SharePress and registering a Facebook Application

= 2.0.4 =
* Fixed: Major bug in setup process, prevented establishing API key and app secret in the database.

= 2.0.3 =
* Fixed: Featured Image feature of SharePress was not working unless the activate Theme supported post-thumbnails. 

= 2.0.2 =
* Fixed: Activating SharePress when the active theme did not use add_theme_support('post-thumbnails') would result in error messages being displayed in the Media management tool and other places

= 2.0.1 =
* Fixed: cron job is working again 
* Fixed: cron job is no longer dependent upon activation/deactivation 
* Fixed: if Facebook error occurs on Settings screen, wp_die is thrown with directions on how to get more information 
* Fixed: no inline error when user has no Pages to manage

= 2.0 =
* SharePress Pro is now available! If you want access to the pro features, you'll need to upgrade SharePress and then buy a license key. This release also fixes a number of bugs and usability issues, and 

= 1.0.10 =
* [Jen Russo](http://www.mauitime.com) reported that posts created via e-mailing to Posterous weren't triggering SharePress. There was a bug that prevented SharePress from firing in all cases other than the ones wherein the user was manually accessing the admin. This is now fixed.

= 1.0.9 =
* [Corey Brown](http://www.twitter.com/coreyweb) reported a bug in the "Publish to Facebook again" feature: not only was it not publishing again, but it was deleting the original meta data. This is now fixed.

= 1.0.8 =
* Ron Kochanowski reported a strange problem with brand new posts displaying the message "This post is older than the date on which SharePress was activated." in the editor. I couldn't fix the problem, so I eliminated the "feature." Problem solved.

= 1.0.7 =
* Added "sp" prefix to the Facebook classes, now "spFacebook" and "spFacebookApiException" - was creating namespace conflicts with other Facebook plugins (thanks [Ben Gillbanks](http://twitter.com/binarymoon) of [WPVOTE](http://www.wpvote.com))

= 1.0.6 =
* Addressing some inconsistencies in the way the plugin is named, and the way that name is used internally.

= 1.0.5 =
* Major typo in the readme. Sheesh.

= 1.0.4 =
* Discovered that the only message that displays within the WordPress plugin library search is under the Description header.

= 1.0.3 =
* Forgot to up the plugin version. Hope I don't make that mistake twice.

= 1.0.2 =
* Admin notices should not display for any users other than administrators
* Broad update to readme.txt

= 1.0.1 =
* Added links for learning more about SharePress Pro

= 1.0 =
* The first release!

== Upgrade Notice ==

= 2.0.15 =
Critical bug fix release. Please upgrade before October 1. Also note that when you upgrade, you will need to run SharePress setup again.

= 2.0.6 =
Critical bug fix release. Please upgrade soon.

= 2.0.4 =
Critical bug fix release. Please upgrade soon.

= 2.0.3 =
Critical bug fix release. Please upgrade soon.

= 2.0.2 =
Critical bug release. Please upgrade soon.

= 2.0 =
SharePress Pro is now available! If you want access to the pro features, you'll need to upgrade SharePress and then buy a license key

= 1.0.6 =
Fixes a bug that results in breaking core JavaScript in the WordPress admin

= 1.0 =
Because it's the first version!