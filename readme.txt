=== Bionic Uptime – Uptime Monitor and Downtime Alerts ===
Contributors: bionicuptime
Tags: uptime, uptime monitor, downtime, monitoring, alerts
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free uptime monitor with downtime alerts. Your site is checked from outside every 60 seconds, and you get one email when it is down.

== Description ==

Bionic Uptime checks your website from outside every 60 seconds, from two locations on two different networks. When both agree the site is down, it sends you one plain email. When the site is back, it sends another.

This plugin puts that status on your WordPress dashboard. It shows the status in words, when the site was last checked, and a link to the full details on bionicuptime.com.

The plugin only reads. The key you paste in lets it read the status of this one website. It cannot change anything in your Bionic Uptime account.

3 websites are free forever. No card needed. Monitoring more than 3 websites needs the Portfolio plan ($12 a month for up to 20). Get a key at [bionicuptime.com/wordpress](https://bionicuptime.com/wordpress).

What the widget will never do:

* Show "Online" from an answer more than about two minutes old. Left open, the dashboard refreshes the widget every 30 seconds, and if it cannot, the widget stops showing a status and says so.
* Guess. If the plugin cannot reach Bionic Uptime, it says so in grey. It does not show the last good status.
* Rely on colour alone. Every status has a shape and words.

The plugin adds no notices, no banners and nothing on the public side of your site.

== Installation ==

1. Install and activate the plugin.
2. Get a free key at [bionicuptime.com/wordpress](https://bionicuptime.com/wordpress).
3. Go to Settings, then Bionic Uptime, and paste the key.
4. Open your dashboard. The Bionic Uptime widget shows the status.

== Frequently Asked Questions ==

= Why is there no "check from inside WordPress"? =

A plugin goes down with the site. When the site is down, the plugin is down too, so it cannot tell you. Bionic Uptime runs its checks from outside your server, so it can still see the site and email you when the site is not answering.

= Does it slow my site? =

No. The plugin makes no calls on the public side of your site. It only asks Bionic Uptime for the status while an administrator has the dashboard open, and at most once a minute.

= What does it catch? =

Your site not answering, error pages, and certificate problems. You can also set a phrase to look for, which catches pages that load but show the wrong thing.

= Who can see the widget? =

Only users who can manage options, which is normally administrators.

= Where do the alerts come from? =

From Bionic Uptime, by email. The plugin does not send email.

= What happens when I delete the plugin? =

It removes the saved key and the cached status from your database.

== Screenshots ==

1. The status on the WordPress dashboard: in words, with when it was last checked and a link to the details.
2. The settings page. The saved key is never shown in full.

== External services ==

This plugin connects to the Bionic Uptime API at bionicuptime.com to read the status of your website. Without it, the plugin has nothing to show.

* What is sent: the key you saved, and your site's address as part of the User-Agent (for example `BionicUptimeWP/1.0.3; https://example.com`).
* When: only after an administrator has saved a key, and only while an administrator has the dashboard open (when it loads, and as the widget refreshes itself). At most once a minute. Nothing is sent from the public side of your site.
* Where: `https://bionicuptime.com/api/v1/status`

The service is operated by Bionics LLC (Bionic Uptime). [Terms of service](https://bionicuptime.com/terms). [Privacy policy](https://bionicuptime.com/privacy).

== Changelog ==

= 1.0.3 =
* The plugin's listed name now says what it does. No code changes.

= 1.0.2 =
* A dashboard left open no longer goes on showing the status it loaded with. The widget refreshes itself every 30 seconds, and once what it shows is over about two minutes old it stops showing a status and says so. A collapsed or hidden widget does not refresh until it is shown again.

= 1.0.1 =
* The free plan now covers 3 websites; the settings page says so.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.3 =
Name only. Nothing in the plugin changed.

= 1.0.2 =
A dashboard left open could go on showing "Online" after it stopped being true. The widget now refreshes itself.

= 1.0.1 =
Wording only: the free plan now covers 3 websites.

= 1.0.0 =
First release.
