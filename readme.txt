=== UplinkPortal ===
Contributors: uplinkportal
Tags: msp, portal, client portal, support, billing
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 0.2.0
License: AGPLv3 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Public core framework for a single-site MSP client portal with modular architecture and secure backend-gated entitlements.

== Description ==

UplinkPortal provides a secure WordPress plugin foundation for an MSP provider operating one hosted portal site for multiple clients.

This public repository includes only core scaffolding:

* Admin IA shell (`Operations Dashboard`, `Agreements & Records`, `Service Catalog`, `Configuration`)
* Configuration hub tabs (`Client Access`, `Modules`, `Security`, `Company Info`, `Portal Preview`)
* Licensing is managed under `Configuration > Modules > Database > Licensing`
* Core module contracts and registry hooks
* REST/API and connector scaffolding
* Role/capability and settings scaffolding
* Custom table storage for per-client access and external ID mapping
* Private portal preview settings storage (`wp-content/uplinkportal-private/portal-preview`)

Premium/paywalled logic is intentionally excluded.
Pro modules require a separate licensed/private add-on.
Custom modules are optional and uploaded at the MSP owner's discretion.
The root plugin owner/company is not liable for custom module code behavior.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/uplinkportal`, or install via the WordPress plugin screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Add the `[uplinkportal]` shortcode to a page.
4. Configure options under `UplinkPortal > Configuration`.

== Frequently Asked Questions ==

= Are premium modules included? =

No. This repository contains only public core scaffolding and placeholders.

= Can I build my own modules? =

Yes. Register modules using the `uplinkportal_modules` filter and implement `UplinkPortal\Core\Modules\ModuleInterface`.

== Changelog ==

= 0.2.0 =
* Merged licensing into first-class core workflows.
* Consolidated settings into unified option schema.
* Added encrypted secret storage wrapper with masked admin fields.

= 0.1.0 =
* Initial public core scaffolding release.

== Upgrade Notice ==

= 0.2.0 =
Merged licensing + settings hard-cutover.
