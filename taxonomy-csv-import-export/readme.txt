=== WP Taxonomy Manager ===
Contributors: rlorakib, freemius
Author URI: https://rlorakib.com/
Plugin URI: https://wordpress.org/plugins/taxonomy-csv-import-export/
Tags: csv import, csv export, taxonomy, custom taxonomy, bulk import
Requires at least: 6.0
Tested up to: 7.2
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WordPress taxonomies and terms with CSV import/export, visual column mapping, bulk editing, custom links, and user transfers.

== Description ==

**WP Taxonomy Manager** brings taxonomy setup, term maintenance, and CSV transfers into one WordPress admin workspace. Work with categories, tags, WooCommerce product taxonomies, and custom taxonomies that your account can manage.

= Free features =

* **CSV term export** - Download term names, slugs, descriptions, parent slugs, and custom links from a selected taxonomy.
* **Export preview** - Review the selected terms in the admin before downloading a file.
* **Export filters** - Narrow exports by empty state, name, parent, or term count.
* **CSV term import** - Create new terms from CSV and skip existing names or slugs; resolve parent slugs against terms already present in the taxonomy.
* **Visual column mapping** - Match source CSV columns to import fields and ignore columns you do not need.
* **Custom taxonomy builder** - Register hierarchical or non-hierarchical taxonomies and attach them to selected post types without code.
* **Term creation** - Add terms from the plugin workspace, including an AJAX form for quick creation.
* **All Terms workspace** - Browse, edit, and delete terms from one admin area, with bulk deletion for selected terms.
* **Inline mass editor** - Search and page through terms, then update names and slugs in place.
* **Custom term links** - Point a term to an internal or external HTTP(S) URL through the term form or a CSV column.
* **Demo term generator** - Create sample terms for development or content planning.
* **User CSV transfer** - Export users by role, then import new accounts or update existing users' profile details. Exports include password hashes and should be stored securely.
* **Dashboard and settings** - View activity counts and adjust the plugin's available options.

= CSV fields =

The Free term CSV uses a required 'name' field and supports 'slug', 'description', 'parent_slug', and 'custom_link'. A parent slug must match an existing term when that row is imported. Import accepts .csv or .txt files with CSV content.

= Pro features =

Pro features require an eligible license or trial.

* **Advanced CSV Import** - Preview row-level errors and warnings, check hierarchy, and choose create-only, update-only, or combined imports before applying changes.
* **XLSX and JSON transfers** - Import and export taxonomy terms in Excel and JSON formats.
* **Term images** - Add or replace Media Library images on term screens, import images from public URLs, include image URLs in CSV exports, and display images with a shortcode.
* **Term ordering** - Drag terms into a custom order and optionally apply that order to frontend term queries.
* **Duplicate term management** - Find likely duplicates, inspect merge impact, merge with confirmation, and restore from the audit history.
* **Scheduled remote CSV sync** - Import a bounded remote CSV on a schedule with optional Basic or Bearer authentication and a recent-run log; requires Action Scheduler.
* **Custom term fields** - Define text, color, image, select, and URL fields and display saved values with a shortcode.
* **Rules-based auto tagging** - Assign terms to posts when configured keywords match.
* **WooCommerce term visuals** - Show brand logos and global attribute color or image swatches when WooCommerce is active.

= Who can use it? =

WP Taxonomy Manager is useful for site administrators moving term data between sites, content teams maintaining large term libraries, developers creating taxonomies, and WooCommerce stores organizing product terms.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via **Plugins → Add New**
2. Activate through the **Plugins** screen
3. Open **WP Taxonomy** in the left admin menu


== Frequently Asked Questions ==

= Which taxonomies are supported? =
Built-in categories and tags, plus custom taxonomies registered by a theme or plugin, including WooCommerce product taxonomies when WooCommerce is active. Your account must have permission to manage the selected taxonomy.

= Can I import terms with parent-child relationships? =
Yes. Include a parent_slug column. In the Free importer, the parent term must already exist when its child row is processed. Pro Advanced Import also checks hierarchy during preview.

= Does this work with large CSV files? =
CSV imports are subject to the upload, memory, and execution limits configured on your WordPress server. Pro dry-run analysis also enforces its configured file-size and row limits. Background batching and retry/resume are not currently included.

= Can I import and export taxonomy term images? =
Yes, with Pro. Add an `image_source` column containing a public HTTP or HTTPS image URL. Import downloads the image into the WordPress Media Library and generates attachment metadata. Export can include the resulting Media Library source URL. WooCommerce product category thumbnails are supported.

= Can a taxonomy term link to a custom page or external website? =
Yes. Enter a Custom Link while adding or editing any taxonomy term, or supply a `custom_link` CSV column. WordPress-generated term links then use that HTTP(S) URL. The feature can be disabled without deleting saved links from WP Taxonomy Manager Settings.


= Can I create a custom taxonomy without writing code? =
Yes. Use the **Register Taxonomy** page to create, name, and attach a taxonomy to any post type directly from the admin UI.

== Screenshots ==

1. Import/Export page — Export tab active with live term preview table
2. Import tab — file upload with success notice and imported terms summary
3. Create Term page — accordion taxonomy list with one row expanded and term creation form
4. Register Taxonomy page — custom taxonomy builder with type selector and post type checkboxes

== Upgrade Notice ==

= 2.0.0 =
Adds term management and Pro workflows, refreshes translations, and strengthens CSV and permission handling.

== Changelog ==

= 2.0.0 - 26/09/2026 =
* Fixed: Plugin Check translation, output escaping, and release package errors.
* Added: Visual CSV column mapping, inline mass term editing, and export filters for name, parent, term count, and empty terms.
* Added: A searchable All Terms workspace with inline editing and bulk deletion.
* Added (Pro): Drag-and-drop term ordering with optional frontend ordering, duplicate merge and restore, and scheduled remote CSV sync with authentication, retries, and a recent-run log.
* Added (Pro): Custom term fields, rules-based auto tagging, WooCommerce brand and attribute visuals, and Media Library term image controls.
* Added (Pro): Add-ons controls for Duplicate Terms, Remote CSV Sync, and WooCommerce integration.
* Improved: Advanced Import now offers create-only, update-only, and combined modes; XLSX and JSON transfers are available with Pro.
* Improved: WordPress.org descriptions now explain shipped Free and Pro features separately.
* Fixed: CSV exports protect spreadsheet formula-like values while retaining import round trips.
* Fixed: Taxonomy and user CSV actions now enforce the relevant WordPress capabilities.

= 0.1 =  
* Initial version

= 0.2 =
* Fixed: Text domain issues
* Fixed: short function naming issue.

= 1.0 =
* Fixed: Plugin Security issues.
* Fixed: Translate issues.

= 1.1 =
* Added: Display of all imported data in a structured table.
* Improved: Implementation of enhanced UI/UX design for a smoother experience.

= 1.2 =
* Fixed: Display of all imported data in a structured table not showing.
* Fixed: Not working Implementation of enhanced UI/UX design.

= 1.3 - 29/03/2026 =
* Added: Users data export/import feature.
* Improved: Plugin menu include for better experience and management.

= 1.4 - 26/05/2026 =
* Added: Register Taxonomy — create and persist custom taxonomies from the admin UI, choosing between Tags (non-hierarchical) or Categories (hierarchical) type and attaching to any post type.
* Added: Create Term — accordion list of all registered taxonomies; expand any row to add a new term with a title and optional description via AJAX (no page reload).
* Added: Export data preview — see all terms in a table before downloading the CSV.
* Added: Unified Import/Export UI with pill-style tab switcher; import and export panels toggle dynamically without a page reload.
* Improved: Full plugin UI reorganisation — consistent page header on every page, card layout with gradient header strip, and bordered/shadowed table containers.
* Improved: Users page redesigned with two side-by-side panels (Export / Import); role selection replaced with checkboxes.
* Improved: CSS rewritten with CSS custom properties for the color scheme; duplicate rules removed and responsive breakpoints consolidated.
* Fixed: `fputcsv()` and `str_getcsv()` deprecated escape-parameter warning (PHP 8.4) that was prepending error output to exported CSV files and corrupting imports.
* Fixed: `ImportLogger::log()` was called with the wrong class name (`Import_Logger`) and as a static method when it was an instance method — both now corrected.
* Fixed: Broken singleton `init()` method that silently created a second unused instance.
* Fixed: `taxocsie_admin_notice_remove()` parenthesis grouping — the third screen ID condition was outside the `isset()` guard, risking a PHP notice on screens without an ID.
* Fixed: `TAXOCSIE_VERSION` constant was mismatched with the plugin header.
* Removed: Facebook footer links from all admin pages.
* Removed: Debug `error_log()` calls left in `autoload.php` and `admin/preview.php`.

= 1.5 - 08/08/2026 =
* Added: A Demo Generator feature to quickly generate sample taxonomy terms for testing and development.
* Added: Premium dry import preview with CSV validation, create/update/skip/error summaries, hierarchy checks, and row-level diagnostics without changing taxonomy data.
* Added: Pro taxonomy image import/export through the `image_source` CSV column, including secure Media Library sideloading, attachment generation, export preview, and WooCommerce product category thumbnail compatibility.
* Added: Custom taxonomy term links with Add/Edit Term fields, Settings control, `term_link` integration, and `custom_link` CSV import/export support.
* Fixed: Plugin activation no longer triggers a fatal error when a source checkout is missing Composer's vendor directory; a fallback internal autoloader and administrator notice are used instead.
* Improved: Third-party admin notices are suppressed consistently across every WP Taxonomy Manager page, including Premium and Settings screens.
* Added: Feature settings for dry-run CSV size, preview row limits, and third-party notice visibility.
* Improved: Demo Generator redesigned with a compact responsive form, clearer option groups, and aligned quick actions.
* Fixed: Demo taxonomy statistics now render Type, Terms, and Actions in their correct table columns.
