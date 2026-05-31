=== Xyjax Consent Logger ===
Contributors: xyjax
Tags: consent, cookies, privacy, gdpr, cookie-banner
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight cookie consent banner with local consent logs and CSV export.

== Description ==

Xyjax Consent Logger adds a simple cookie consent banner and stores visitor consent choices locally in your WordPress database.

The plugin is designed to avoid telemetry, external services, tracking pixels, remote code, and upsell notices. Consent records are stored on the site where the plugin is installed.

Features include:

* Accept all, reject non-essential, and customize buttons.
* Consent categories for necessary, analytics, marketing, and affiliate cookies.
* Local consent log table.
* Admin log viewer.
* CSV export for site administrators.
* Privacy policy helper text.
* Shortcode for reopening cookie preferences: [xyjax_consent_preferences]
* Uninstall cleanup.

This plugin does not provide legal advice. Site owners are responsible for configuring their site and privacy disclosures for their own legal requirements.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/xyjax-consent-logger` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > Xyjax Consent to configure the banner text, privacy policy URL, consent version, and retention period.

== Frequently Asked Questions ==

= Does this plugin send data to Xyjax? =

No. This plugin does not send consent records, usage data, site URLs, or analytics data to Xyjax or any external service.

= Does this plugin block third-party scripts automatically? =

This first version records consent choices and exposes a browser event named `xyjaxConsentSaved`. Developers can use that event to load scripts after consent. Automatic third-party script blocking is planned for a later version.

= Where are consent logs stored? =

Consent logs are stored locally in a custom WordPress database table named with your WordPress database prefix, followed by `xyjax_consent_logs`.

= Does uninstalling remove data? =

Yes. Uninstalling the plugin removes the plugin settings and consent log table.

== Screenshots ==

1. Cookie consent banner.
2. Settings page.
3. Consent log viewer.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
