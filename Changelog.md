# **Bimonthly Report Manager Changelog**

Oct 5, 2026 · @SCJ

## **2.3.6 — Oct 5, 2026**

Upgrades from 2.3.3. Files changed: `bimonthly-report-manager.php`, `includes/admin-page.php`, `js/bimonthly-report-manager.js`.

### **Changed**

* **Period windows shifted.** Each period now pivots between its two months. Past Highlights cover the month before the period plus its first month; Future Highlights cover its second month plus the month after. Example: Sep–Oct now pulls Past from Aug–Sep and Future from Oct–Nov (previously Sep–Oct and Nov–Dec). Year rollover is handled for Jan–Feb and Nov–Dec. (`get_period_ranges()`)  
* **Period dropdowns show the spans.** The New Update modal and the ✎ period editor list each period with its Past/Future months, built from one shared list. (`$periods`, new `get_periods()`, `admin-page.php`)  
* **Sidebar shows Past/Future months.** The line under each update title reads e.g. "Past: Aug–Sep 2026 · Future: Oct–Nov 2026" instead of the period name. Sort order is unchanged. (`ajax_get_bimonthly_list()`)  
* **Period chip shows Past/Future months.** The yellow chip in the update editor uses the same format and updates immediately after saving a new period. (new `span_label` in `get_period_ranges()`; `renderReport` and `savePeriod`)  
* **Period editor pre-selects.** The ✎ editor opens with the update's current period and year instead of a blank period and the current year. (`currentPeriod` in the JS)  
* **Version bump.** Plugin header and `BRM_VERSION` both set to 2.3.6 (previously mismatched at 2.3.4 / 2.3.3). This forces browsers to load the new JS.

### **Notes**

* No database changes. Stored period keys (`sep-oct`, etc.) are unchanged, so existing updates use the new windows automatically.  
* Items saved under the old windows still display and export correctly, but may not appear in the post picker if edited.  
* After deploying, clear any caching plugin or CDN and hard-refresh the Bimonthly Updates page.

### **Known issues**

* Group names containing `&` display as `&amp;` in the sidebar (e.g. Northeast `&amp;` Caribbean ATTC). Fix identified, not yet applied.  
* Minor cleanup pending: an unused `var text` line in `showPeriodEditor`, and the sidebar builds its span text separately rather than reusing `span_label`. No visible effect.

