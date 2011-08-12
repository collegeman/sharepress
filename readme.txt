=== Plugin Name ===
Contributors: aaroncollegeman
Donate link: http://aaroncollegeman.com/sharepress
Tags: social, facebook, publicize, automate, marketing
Requires at least: 2.9
Tested up to: 3.2.1
Stable tag: 2.0.4

Sharepress publishes your content to your Facebook Wall. 

== Description ==

Sharepress publishes your content to your Facebook Wall. 

Reaching an audience of more than 500,000,000 people, Facebook has become the next great stage from which to speak to the world. Rest assured: your customers and your readers are there - past, present, and future. 

But if you're like us, then posting things on Facebook is somewhere at the bottom of your list. After all, you're creating quality content with WordPress, and who has time to keep one eye on Facebook all day long? No longer will you have to manually share links to your Posts - just click Publish, and let Sharepress do the rest.

There are two versions of Sharepress: a lite version, that lets you post to your personal Facebook Wall; and a pro version, that lets you post to any Facebook Page you manage, as well as customize the messages that are posted there.

== Installation ==

1. Get the plugin-in. Download it manually or install through the 'Plugins' menu in WordPress.

2. If you downloaded the plugin as a zip file, extract the plugin into your `wp-content/plugins` folder. This should create within the plugins folder another folder named `sharepress`. If it does not create this folder, you must create the folder yourself, and put the plugin files in it. Sharepress will not work unless it resides in a folder named `sharepress`.

3. Activate the plugin through the 'Plugins' menu in WordPress.

4. Configure through the 'Sharepress' item on the 'Settings' menu. Instructions in the application will help you to create your own Facebook Application (required).

Post questions about installation and usage [in the forum](http://wordpress.org/tags/sharepress?forum_id=10#postform).

== Frequently Asked Questions ==

= How do I get the pro version? =

If you have Sharepress installed, all you need to do is [buy a key](http://aaroncollegeman.com/sharepress).

== Changelog ==

= 2.0.4 =
* Fixed: Major bug in setup process, prevented establishing API key and app secret in the database.

= 2.0.3 =
* Fixed: Featured Image feature of Sharepress was not working unless the activate Theme supported post-thumbnails. 

= 2.0.2 =
* Fixed: Activating Sharepress when the active theme did not use add_theme_support('post-thumbnails') would result in error messages being displayed in the Media management tool and other places

= 2.0.1 =
* Fixed: cron job is working again 
* Fixed: cron job is no longer dependent upon activation/deactivation 
* Fixed: if Facebook error occurs on Settings screen, wp_die is thrown with directions on how to get more information 
* Fixed: no inline error when user has no Pages to manage

= 2.0 =
* Sharepress Pro is now available! If you want access to the pro features, you'll need to upgrade Sharepress and then buy a license key. This release also fixes a number of bugs and usability issues, and 

= 1.0.10 =
* [Jen Russo](http://www.mauitime.com) reported that posts created via e-mailing to Posterous weren't triggering Sharepress. There was a bug that prevented Sharepress from firing in all cases other than the ones wherein the user was manually accessing the admin. This is now fixed.

= 1.0.9 =
* [Corey Brown](http://www.twitter.com/coreyweb) reported a bug in the "Publish to Facebook again" feature: not only was it not publishing again, but it was deleting the original meta data. This is now fixed.

= 1.0.8 =
* Ron Kochanowski reported a strange problem with brand new posts displaying the message "This post is older than the date on which Sharepress was activated." in the editor. I couldn't fix the problem, so I eliminated the "feature." Problem solved.

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
* Added links for learning more about Sharepress Pro

= 1.0 =
* The first release!

== Upgrade Notice ==

= 2.0.4 =
Critical bug fix release. Please upgrade soon.

= 2.0.3 =
Critical bug fix release. Please upgrade soon.

= 2.0.2 =
Critical bug release. Please upgrade soon.

= 2.0 =
Sharepress Pro is now available! If you want access to the pro features, you'll need to upgrade Sharepress and then buy a license key

= 1.0.6 =
Fixes a bug that results in breaking core JavaScript in the WordPress admin

= 1.0 =
Because it's the first version!