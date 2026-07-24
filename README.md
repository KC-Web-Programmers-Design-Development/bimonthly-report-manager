# Bimonthly Report Manager

A WordPress plugin for managing bimonthly network updates: editors select past and upcoming activity from existing content, tag it to workplan outputs, and export the result as a branded PDF — either per-group or as a combined "meta report" across all groups.

- **Version:** 2.1.0
- **Author:** KC Web Programmers
- **Text Domain:** `bimonthly-report-manager`

## Features

- **Bimonthly update posts** — custom `bimonthly` post type, one per group/reporting period, holding a "Past Highlights" and "Future Highlights" section (max 3 items each).
- **Date-filtered post selection** — pulls candidate items from `news`, `event`, `products_and_resourc`, and `bimonthly-highlight` post types, filtered to a configurable window (2 or 3 months) before/after the current date.
- **Cascading workplan tagging** — each selected item can be linked to a Workplan → Goal → Objective → Output chain via ACF relationship fields, drilled down through AJAX calls.
- **Inline highlight creation** — create a new `bimonthly-highlight` post directly from the item picker without leaving the page.
- **AI-generated summaries** — if no manual summary exists, content is sent to OpenAI (`gpt-4o-mini`) to generate a sub-100-word summary, cached back onto the source post (`internal_reporting_summary` field).
- **Group/region-based permissions** — a custom `edit_bimonthly_updates` capability, plus a group → editor role → region number mapping (configured in Settings), restricts which updates a given editor can see and edit, and orders the meta report by region.
- **PDF export** — per-update and combined "meta report" exports rendered by a Python (`reportlab`) generator (`includes/generate-pdf.py`), invoked via `shell_exec`, with an uploadable cover logo.
- **Meta report preview** — HTML preview of the combined report (grouped by center/region, with optional type/author/date metadata) before export.
- **Frontend shortcode** — `[bimonthly_report id="123"]` renders a given update's Past/Future Highlights tables on the front end.

## Requirements

- WordPress with **Advanced Custom Fields (ACF)** — used for relationship fields (`related_work_plan_goals`, `work_plan_objectives`, `objective_outputs`, etc.) and post meta (falls back to plain post meta if ACF is inactive).
- Custom post types expected to already be registered elsewhere (theme or another plugin): `bimonthly`, `bimonthly-highlight`, `workplan`, `goal`, `objective`, `news`, `event`, `products_and_resourc`.
- A `group` taxonomy, used for access control and report region ordering.
- **Python 3** with the `reportlab` package installed and reachable on the server (`python3`/`/usr/bin/python3`), required for PDF export.
- An OpenAI API key stored in the `chatgpt_api_key` WordPress option (no in-plugin settings field for this yet — set via `wp option update chatgpt_api_key "sk-..."` or another settings mechanism) to enable AI summaries. Without it, summaries are simply left blank.

## Setup

1. Install and activate the plugin. Activation grants the `edit_bimonthly_updates` capability to Administrators automatically.
2. Go to **Bimonthly Updates → Settings**:
   - Map each `group` taxonomy term to an editor role and a region number, then click **Apply Capabilities to Roles** to grant `edit_bimonthly_updates` to the mapped roles.
   - Set the prior/ahead date window (2 or 3 months).
   - Upload a logo for the PDF cover page.
3. (Optional) Set the `chatgpt_api_key` option to enable AI-generated summaries.

## Usage

- **Bimonthly Updates** (admin menu) — create/select an update, pick items for Past and Future Highlights, tag workplan outputs, and export a PDF.
- **Meta Report** (submenu, requires `edit_others_workplans`) — select multiple updates across groups, preview, and export a combined, region-ordered PDF.
- **`[bimonthly_report id="123"]`** shortcode — display a published update's highlights on any page or post.

## Version History

- **2.1.0** — current version (no prior changelog recorded).
