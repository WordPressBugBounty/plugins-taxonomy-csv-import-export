=== Taxonomy CSV Import Export ===
Contributors: rlorakib
Author URI: https://rlorakib.com/
Plugin URI: https://wordpress.org/plugins/taxonomy-csv-import-export/
Tags: csv import, csv export, taxonomy, custom taxonomy, bulk import
Requires at least: 5.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import and export WordPress taxonomy terms and users via CSV. Register custom taxonomies, create terms, and preview exports — all from the admin.

== Description ==

**Taxonomy CSV Import Export** is the complete admin toolkit for managing WordPress taxonomies and users through CSV files. Import hundreds of terms in seconds, export any taxonomy with a single click, build custom taxonomies without writing a line of code, and manage your entire user base — all from a clean, modern admin interface.

Whether you are migrating a site, seeding a staging environment, bulk-editing taxonomy terms, or onboarding users, this plugin removes the manual work entirely.

Works with every taxonomy WordPress supports: built-in categories and tags, WooCommerce product categories, and any custom taxonomy registered by a theme or plugin.

---

= 🗂️ Feature 1 — CSV Export =

Export any registered taxonomy to a structured CSV file in one click.

**What it does:**
Select a taxonomy from the dropdown, optionally preview the full term list in a table, then download the CSV. The exported file includes every term's name, slug, description, and parent slug so the data is ready to import into any other WordPress site.

**Use cases:**
* Back up all taxonomy terms before a major site update
* Migrate categories or tags from a staging site to production
* Hand off a taxonomy list to a content team for bulk editing in Excel or Google Sheets
* Archive taxonomy data as part of a site documentation process

**Exported columns:** `name`, `slug`, `description`, `parent_slug`

---

= 🔍 Feature 2 — Export Preview =

See exactly what will be exported before the file downloads.

**What it does:**
Click **Preview** to load a live table of all terms for the selected taxonomy — name, slug, description, and parent slug — directly on the page. No download needed to verify the data.

**Use cases:**
* Confirm the correct taxonomy is selected before downloading
* Spot missing descriptions or broken parent relationships before handing off data
* Show a client or team member what data exists without needing to open a spreadsheet

---

= 📥 Feature 3 — CSV Import =

Bulk-import taxonomy terms from any CSV file with automatic hierarchy resolution.

**What it does:**
Upload a CSV file with a `name` column (plus optional `slug`, `description`, and `parent_slug`). The importer processes every row, creates the term, links it to its parent by slug, and shows a per-term results table — created, updated, skipped, or failed — when complete.

**Use cases:**
* Populate a brand-new site with hundreds of categories from a spreadsheet
* Migrate taxonomy terms from another CMS or platform exported as CSV
* Re-import an edited export file after a content team has updated descriptions in Excel
* Seed a development or staging environment to match the production taxonomy structure
* Sync taxonomy terms across multiple WordPress installations

**Supported CSV columns:** `name` (required), `slug`, `description`, `parent_slug`

---

= 🏷️ Feature 4 — Register Custom Taxonomy =

Create and persist custom taxonomies from the WordPress admin — zero code required.

**What it does:**
Fill in a label, singular label, slug, and optional description. Choose the taxonomy type — **Tags** (non-hierarchical, like post tags) or **Categories** (hierarchical, like post categories). Select the post types to attach it to. Click Register. The taxonomy is saved to the database and re-registered automatically on every page load.

**Use cases:**
* Build a "Genres" taxonomy for a movie review site without editing `functions.php`
* Add a "Topics" taxonomy to a knowledge base that attaches to both posts and pages
* Let a non-developer client set up their own taxonomy structure during onboarding
* Quickly prototype a custom post type taxonomy during theme development
* Replace hardcoded taxonomy registrations with a UI-managed alternative that survives theme switches

**Options:** Label, Singular Label, Slug, Description, Type (Tags / Categories), Post Types

---

= ✏️ Feature 5 — Create Term =

Add new terms to any taxonomy directly from the admin — no page reload.

**What it does:**
The Create Term page shows an accordion list of every registered taxonomy. Expand a taxonomy row, type a term title and optional description, and click **Create Term**. The term is saved instantly via AJAX and a confirmation message appears in place — the page never reloads.

**Use cases:**
* Quickly add a handful of new tags or categories without navigating to the taxonomy list screen
* Let an editor add terms to a custom taxonomy they do not have full edit access to
* Add terms to multiple taxonomies in a single session without switching between admin screens
* Test that a newly registered custom taxonomy is working before importing data into it

---

= 👥 Feature 6 — User Import & Export =

Export and import WordPress users by role with a clean two-panel interface.

**What it does:**
The Users page is split into two side-by-side panels. The Export panel lets you select one or more user roles using checkboxes (or export all users if none are selected) and download a CSV. The Import panel accepts a CSV file and creates or updates user accounts automatically.

**Use cases:**
* Export all Editor-role users before transferring a site to a new host
* Back up your entire user database as a CSV for offline storage
* Migrate users from one WordPress site to another without a full database export
* Onboard a large number of new users by preparing a CSV and importing in one step
* Re-import updated user data after a team has edited roles or details in a spreadsheet

---

= ✅ Why Choose This Plugin? =

* **Zero configuration** — activate and start working immediately, no settings page needed
* **Preview before download** — verify export data in a table before committing to a file
* **Full hierarchy support** — parent–child term relationships preserved on import and export
* **No-code taxonomy builder** — register custom taxonomies entirely through the admin UI
* **Role-aware user export** — filter users by one or more roles or export everyone at once
* **AJAX-powered** — term creation and export preview happen without page reloads
* **PHP 8.4 compatible** — no deprecated-function warnings on the latest PHP release
* **Translation ready** — all user-facing strings use WordPress i18n functions

= Who Is This For? =

* **Site administrators** who need to migrate or back up taxonomy data without developer help
* **WordPress developers** seeding staging environments or prototyping taxonomy structures quickly
* **Content managers** responsible for maintaining large category or tag libraries
* **Agency teams** delivering client sites that require custom taxonomy structures built in the admin
* **WooCommerce store owners** managing large product category trees

= How It Works =

1. Go to **Taxonomy CSV** in the WordPress admin sidebar
2. **Export** — select a taxonomy, click Preview to review, then Export CSV to download
3. **Import** — switch to the Import tab, upload your CSV, and click Import CSV
4. **Register Taxonomy** — fill in the form to create a new custom taxonomy
5. **Create Term** — expand a taxonomy row, enter a title, and click Create Term
6. **Users** — use the Export or Import panel to manage your user base by role

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via **Plugins → Add New**
2. Activate through the **Plugins** screen
3. Navigate to **Taxonomy CSV** in the left admin menu

== Frequently Asked Questions ==

= Which taxonomies are supported? =
All taxonomies registered in WordPress — built-in (category, post_tag) and custom ones from any theme or plugin, including WooCommerce product categories.

= Can I import terms with parent–child relationships? =
Yes. Include a `parent_slug` column in your CSV. The importer resolves the parent by slug automatically.

= Does this work with large CSV files? =
Yes. The importer processes rows one at a time so memory usage stays flat regardless of file size.

= Is it compatible with PHP 8.4? =
Yes. The deprecated `escape` parameter for `fputcsv()` and `str_getcsv()` has been removed to eliminate warnings on PHP 8.4.

= Can I create a custom taxonomy without writing code? =
Yes. Use the **Register Taxonomy** page to create, name, and attach a taxonomy to any post type directly from the admin UI.

== Screenshots ==

1. Import/Export page — Export tab active with live term preview table
2. Import tab — file upload with success notice and imported terms summary
3. Create Term page — accordion taxonomy list with one row expanded and term creation form
4. Register Taxonomy page — custom taxonomy builder with type selector and post type checkboxes

== Upgrade Notice ==  
N/A

== Changelog ==  
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