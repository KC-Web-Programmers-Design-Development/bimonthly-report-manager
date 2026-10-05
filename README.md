# Bimonthly Report Manager

**Version:** 2.3.6
**Author:** KC Web Programmers
**Text domain:** `bimonthly-report-manager`

A WordPress plugin for building and exporting bimonthly updates. Each update has two sections, **Past Highlights** and **Future Highlights**. Editors fill them by picking existing site content (news, events, products and resources, and bimonthly highlights) and linking it to workplan outputs. Updates can be exported as branded narrative-style PDFs, individually or combined into a network-wide Meta Report.

## Features

- **Update manager** with a sidebar list of updates and an editor for Past and Future highlights.
- **Period-based date windows.** Choose a reporting period and year, and the post picker only offers content from the matching date ranges (see [Reporting periods](#reporting-periods)).
- **Cascading workplan selection.** Link each item to a workplan, goal, objective and output.
- **Summary editing.** Edit the internal reporting summary for an item directly in the editor.
- **Group-based access.** Map WordPress roles to `group` taxonomy terms so each center only sees its own updates.
- **PDF export** for a single update, with a configurable cover page.
- **Meta Report** that combines selected updates from multiple groups into one PDF, ordered by region.
- **Workplan connections metabox** on post edit screens, showing which updates reference that post (read-only).
- **Shortcode** `[bimonthly_report]` to display an update's highlights as tables on the front end.

## Requirements

| Requirement | Notes |
|---|---|
| WordPress | A recent 6.x release is recommended. |
| Advanced Custom Fields | The plugin calls `get_field()` for event, publication and highlight dates. |
| Post types | `bimonthly`, `news`, `event`, `products_and_resourc`, `bimonthly-highlight` |
| Taxonomy | `group` |
| Capability `edit_others_workplans` | Treated as "network admin". Provided by your workplan setup, not by this plugin. |
| Python 3 with [ReportLab](https://pypi.org/project/reportlab/) | Required for PDF export. Must be installed on the server. |
| PHP `shell_exec` | Must be enabled. PDF generation runs `includes/generate-pdf.py` through the shell. |

The custom post types, taxonomy and workplan data are expected to be registered by the site's theme or other plugins.

## Installation

1. Copy the `bimonthly-report-manager` folder to `wp-content/plugins/`, or upload the zip under **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. Activation grants the `edit_bimonthly_updates` capability to Administrators.
3. Install ReportLab for the server's Python 3 (`pip3 install reportlab`).
4. Go to **Bimonthly Updates → Settings**:
   - Map each group to a role (and region, for Meta Report ordering).
   - Click **Apply Capabilities** to grant `edit_bimonthly_updates` to the mapped roles.
   - Set the PDF cover page logo and network title.

> **Deactivation** removes `edit_bimonthly_updates` from every role. Re-run **Apply Capabilities** after reactivating to restore access for non-admin roles.

## Usage

### Creating an update
1. Open **Bimonthly Updates** in the admin menu.
2. Click **+ New Update**, then choose a group, reporting period and year.
3. In the editor, add items under **Past Highlights** and **Future Highlights**. The picker is filtered to the date window for that direction.
4. Edit summaries as needed. Items are saved automatically as you work.
5. Use the **✎** button beside the period chip to change the period or year. The editor opens with the current values selected.

### Reporting periods

Each period pivots between its two months. **Past** covers the month before the period plus its first month. **Future** covers its second month plus the month after.

| Period | Past highlights | Future highlights |
|---|---|---|
| Jan – Feb | Dec – Jan | Feb – Mar |
| Mar – Apr | Feb – Mar | Apr – May |
| May – Jun | Apr – May | Jun – Jul |
| Jul – Aug | Jun – Jul | Aug – Sep |
| Sep – Oct | Aug – Sep | Oct – Nov |
| Nov – Dec | Oct – Nov | Dec – Jan |

Year boundaries are handled automatically. For example, Jan – Feb 2027 gives Past: Dec 1, 2026 – Jan 31, 2027 and Future: Feb 1 – Mar 31, 2027.

Updates created without a period fall back to a rolling two-month window around today's date.

### Exporting
- **Single update:** use the export button in the update editor.
- **Meta Report:** go to **Bimonthly Updates → Meta Report**, select updates, preview, and export one combined PDF. This page requires `edit_others_workplans`.

### Shortcode

```
[bimonthly_report id="123"]
```

Renders **Past Highlights** and **Future Highlights** tables for the given `bimonthly` post. If `id` is omitted, the current post is used. Front-end styles load from `css/bimonthly-frontend.css`.

## Permissions

| Capability | Used for |
|---|---|
| `edit_bimonthly_updates` | Access to the Bimonthly Updates screen, and creating updates and highlights. |
| `edit_others_workplans` | Network-wide access (all groups), the Meta Report page and PDF export. |
| `manage_options` | Settings page, group/role configuration and applying capabilities. |

Users without `edit_others_workplans` are limited to updates tagged with a `group` term mapped to one of their roles.

## File structure

```
bimonthly-report-manager/
├── bimonthly-report-manager.php   # Main plugin class, AJAX handlers, shortcode, metabox
├── Changelog.md
├── README.md
├── css/
│   ├── bimonthly-report-manager.css   # Admin styles
│   └── bimonthly-frontend.css         # Shortcode styles
├── includes/
│   ├── admin-page.php        # Main update manager UI
│   ├── settings-page.php     # Group/role config and PDF cover settings
│   ├── meta-report-page.php  # Meta Report generator UI
│   └── generate-pdf.py       # ReportLab PDF generator
└── js/
    └── bimonthly-report-manager.js    # Admin UI logic
```

## Data storage

No custom tables. Each update is a `bimonthly` post with this meta:

| Meta key | Contents |
|---|---|
| `_brm_period` | Period key (`jan-feb`, `mar-apr`, `may-jun`, `jul-aug`, `sep-oct`, `nov-dec`) |
| `_brm_year` | Four-digit year |
| `_brm_prior_items` | JSON list of Past Highlight items |
| `_brm_ahead_items` | JSON list of Future Highlight items |

Plugin settings are stored in the `brm_group_config` option and the PDF cover settings.

## Upgrading to 2.3.6

- No database changes. Existing updates pick up the new date windows automatically, because only the period key is stored.
- Items saved under the old windows still display and export, but may not appear in the post picker when you edit the update.
- Clear any caching plugin or CDN and hard-refresh the Bimonthly Updates page after deploying.

See [Changelog.md](Changelog.md) for details.

## Known issues

- Group names containing `&` display as `&amp;` in the sidebar.

## License

Proprietary. Developed by KC Web Programmers for client use. *(Update this section if you publish under an open-source license.)*
