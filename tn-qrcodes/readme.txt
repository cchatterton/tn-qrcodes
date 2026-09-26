=== TN QR Codes ===
Contributors:
Tags: techn
Requires at least: 7.0
Tested up to: 7.1.2
Stable tag: 1.5.1
Requires PHP: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds QR codes to public post types and allows tracked QR code downloads.

== Description ==

Adds QR codes to public post types and allows tracked QR code downloads.

== Installation ==

1. Upload tn-qrcodes.zip through Plugins > Add Plugin > Upload Plugin.
2. Activate the plugin in the same site or network scope as your existing installation.
3. Configure its existing feature settings as usual.

== Changelog ==

= 1.5.1 =
* Replace the independent updater with TN Update Controller integration.
* Standardise author and plugin-row links; preserve feature settings and plugin identity.
* Require WordPress 7.0+ and PHP 8.5+.


== Managed updates ==

Install and activate TN Update Controller to discover and install updates. The plugin row offers Install Techn Update Controller, Activate Techn Update Controller, or Check for updates according to local state and permissions. Feature operation does not require the controller. No release lookup happens while rendering this plugin's row. On multisite the controller must be network active. This plugin release requires WordPress 7.0 and PHP 8.5 or later.

== Controller installation service ==

Only an explicit authorised Install Techn Update Controller action downloads the official controller ZIP from GitHub. No plugin settings or site inventory are submitted; GitHub receives the server IP address and normal request metadata. Routine update discovery is delegated to the installed controller. Repository links open GitHub when selected.
Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
