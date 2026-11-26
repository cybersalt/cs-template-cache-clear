# Changelog

All notable changes to Cybersalt Template Cache Clear.

## Unreleased (1.0.0)

### 🚀 New
- **Clear cache on save**: empties Joomla's page cache when a site template style is saved (Save and Save & Close), so visitors see the change on their next page load.
- **Default style changes**: also empties it when a style is made the default, or a language default is removed, in Site Template Styles.
- **Per-style switch**: a **Cache** tab with a **Clear cache on save** switch (on by default) on every site template style. A template can declare its own `clearcacheonsave` field instead.
- **Message after clearing** (can be turned off), shown when the System - Page Cache plugin is on.
- Covers saves made through the Web Services API and the front-end Template Settings screen.

### 📦 Build
- `build.ps1` builds the package with 7-Zip and writes the integrity file list.
- Shipped-file integrity check on the settings page.
- Switches itself on at first install, with a post-install card linking to Site Template Styles and the plugin settings.
