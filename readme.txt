=== Utility Pack for WP All Export ===
Contributors: codingchicken
Donate link:
Tags: wpcli,wp all export,export
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.2
Requires PHP: 7.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhance your WP All Export experience with a pack of helpful utilities such as WP-CLI support.

== Description ==

Make WP All Export even more powerful with this utility pack. The initial release includes the often requested WP-CLI support.

Amplify the speed of your WP All Export jobs by running them with [WP-CLI](https://make.wordpress.org/cli/handbook/).

== Features ==

= WP-CLI Support =
* Run WP All Export jobs via WP-CLI
* Works with both WP All Export free and WP All Export Pro plugins
* Resume incomplete exports without the need to start them again from the beginning
* Maximize the speed of your WP All Export jobs by running them via the command line

= Future =
* The initial release includes only WP All Export WP-CLI support, but we're already building additional functionality for future releases.
* Have a feature in mind? Let us know under the Support tab and we'll see what we can do.

== Frequently Asked Questions ==

= What version of WP All Export do I need to use this? =

The Utility Pack is made to support both free and Pro versions of WP All Export unless otherwise specified for a specific feature.

= How do I run a WP All Export job with WP-CLI? =

Load your installation for WP-CLI as usual and run the command
```
wp utility-all-export run <export-id>
```

where `<export-id>` is the export you want to run. Such as
```
wp utility-all-export run 1
```

Additional command options can be found with `wp help utility-all-export` within WP-CLI.

== Screenshots ==

1. Easily use WP-CLI to run WP All Export jobs you've previously configured.
2. A progress bar or spinner will be displayed when running WP All Export jobs.
3. You can easily continue a WP All Export job in progress or restart it using the appropriate flag.

== Changelog ==

= 1.0.2 =
* improvement: add 'coding_chicken_wpae_cli_records_per_iteration' filter to control records per iteration setting when running via WP-CLI

= 1.0.1 =
* improvement: set records per iteration to 100000 while running via WP-CLI

= 1.0 =
* initial release

