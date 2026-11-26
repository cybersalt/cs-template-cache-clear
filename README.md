# Cybersalt Template Cache Clear

A tiny Joomla 5 and Joomla 6 system plugin that empties Joomla's page cache when a site template style is saved or made the default, so visitors see the change on their next page load instead of when the cached pages expire (15 minutes by default).

## The problem it solves

With Joomla's **System - Page Cache** plugin on, every page is kept as finished HTML. Change a template setting and click **Save & Close**, and visitors keep getting the old look until the cached pages run out. Joomla empties its template caches on save, but not the page cache. A template can't fix this by itself: a cached page never runs the template's `index.php`, and after Save & Close the edit form never loads again.

## What it does

- Adds a **Cache** tab with a **Clear cache on save** switch (on by default) to the edit screen of every **site** template style. Administrator templates are left alone.
- When a style with the switch on is saved (**Save** or **Save & Close**), it empties the `page` cache group.
- Also runs when a style is made the default (or a language default is removed) from **System, Site Template Styles**. Joomla fires no save event for that, so the plugin watches the cache clean Joomla does right after.
- Saves made through the Web Services API (or MCP for Joomla) and the front-end **Template Settings** screen go through the same model, so they are covered too.
- Shows a short message after clearing, when the System - Page Cache plugin is on (can be turned off).
- Uses the cache handler and cache folder from Global Configuration (file, Redis, Memcached, APCu).
- Switches itself on when first installed.
- Shipped-file integrity check on its settings page.

It does not purge a CDN (Cloudflare and others) or another caching extension. The tab text tells the site owner to clear those themselves.

## For template developers

A template can declare the switch in its own `templateDetails.xml`, in any fieldset of `params`. The plugin then uses that field and doesn't add its own tab:

```xml
<field name="clearcacheonsave" type="radio" default="1"
       layout="joomla.form.field.radio.switcher" filter="integer"
       label="TPL_EXAMPLE_CLEARCACHEONSAVE_LABEL"
       description="TPL_EXAMPLE_CLEARCACHEONSAVE_DESC">
    <option value="0">JNO</option>
    <option value="1">JYES</option>
</field>
```

A style saved before the switch existed has no value and is treated as **on**.

## Requirements

- Joomla 5.0 or newer (5.x and 6.x)
- PHP 8.1 or newer

## Installation

1. Download the latest release ZIP file.
2. In Joomla admin, go to **System, Install, Extensions** and upload it.
3. That's it: the plugin switches itself on. Open any site template style to see the **Cache** tab.

## Building from source

```powershell
.\build.ps1
```

Writes `integrity.json`, then zips the plugin with 7-Zip into `dist\plg_system_cstemplatecacheclear_v<version>_<YYYYMMDD>_<HHMM>.zip`.

## License

GNU General Public License version 2 or later.

## Author

[Cybersalt](https://www.cybersalt.com) · support@cybersalt.com
