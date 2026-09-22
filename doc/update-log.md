# Update Log

## 2026-09-22
- Fixed the "Disable auto-logout" checkbox resetting on every page load; it now retains its checked state across refreshes for the current browser session.
- Author: GitHub Copilot

## 2026-09-19
- Added the public `/index.php` start page, which reads visible tabs, headers, and links from the database and presents them in a tab-style layout.
- Updated the start page to use four columns and position headers by their database `column` and `row` values; headers sharing a position are sorted by `headername`.
- Restyled the public start page with a dark dashboard layout, compact tab navigation, and denser link panels inspired by the supplied start.me reference.
- Added session-based admin login/logout protection and restricted `hidefrompublic` tabs to authenticated sessions.
- Fixed the public Admin and Log out links to resolve relative to the MyStartPage installation folder.
- Fixed public tab queries so authenticated sessions correctly load tabs marked `hidefrompublic`.
- Updated logout to return to the public `/index.php` page instead of the admin login page.
- Moved the public Admin and Log out actions to the upper-right of the page header.
- Added a View MyStartPage link to the admin page.
- Moved the admin navigation links to the upper-right of the admin header.
- Restyled the admin panel and login screen to match the public dashboard look and feel.
- Moved the public and admin visual styles into the shared `/config/lookandfeel.css` stylesheet.
- Expanded the public page grid to use the full available page width.
- Removed the remaining public-page side margins so all four columns span the complete viewport.
- Fixed the public header markup so the four-column grid is no longer constrained inside the header content area.
- Sorted public-page tabs by `hidefrompublic` ascending and `tabname` descending.
- Made public header row tracks explicit so headers align across all four columns.
- Rendered each database header row as a full-width four-column band to preserve alignment across empty cells.
- Switched the public header grid to fixed table geometry so all header rows share exact column alignment.
- Normalized header-table edge spacing so the fourth column uses the same width and alignment as the other columns.
- Changed public headers to independent four-column stacks so each header starts directly below the previous header in its column.
- Added dependent Tab and Header dropdowns when adding or editing links in the admin panel.
- Added a hostname fallback for link names when a website does not expose a readable page title.
- Reserved the same favicon space for links without a favicon so link labels stay aligned.
- Limited displayed public link labels to 128 characters with an ellipsis for longer names.
- Limited displayed target URLs in the admin Links table to 128 characters with an ellipsis.
- Wrapped displayed target URLs in the admin Links table after every 20 characters.
- Expanded the admin page main layout to use the complete viewport width.
- Slightly reduced the Page name font size in the admin Links table.
- Set the admin Links table Page name column to 100 pixels wide.
- Increased the admin Links table Page name column to 200 pixels wide.
- Increased the admin Links table Page name column to 300 pixels wide.
- Set the admin Links table Actions column to 200 pixels wide.
- Reduced the admin Links table Actions column to 150 pixels wide.
- Changed the admin Links table Favicon URL column to display favicon images instead of URL text.
- Standardized all displayed favicons to 16x16 pixels on the public and admin pages.
- Constrained public favicon images to the reserved 16x16 slot while preserving their aspect ratio.
- Set the admin Links table Favicon column to 150 pixels wide and renamed its heading to Favicon.
- Reduced the admin Links table Favicon column to 100 pixels wide.
- Reduced the admin Links table Favicon column to 75 pixels wide.
- Allowed the admin Links table URL column to fill the remaining available table width.
- Replaced raw header IDs in the admin Links table with the corresponding tab and header names.
- Replaced displayed link URLs in the admin Links table with buttons that open the target page in a new tab.
- Removed visible ID columns from admin tables and moved Actions to the first column.
- Moved Favicon to the second displayed column in the Links table and widened Actions to 175 pixels.
- Set the displayed Header column in the Links table to 250 pixels wide.
- Normalized remote page titles to UTF-8 before saving them, preventing invalid-character errors for non-UTF-8 websites.
- Improved automatic page-title retrieval with browser-like requests and Open Graph/Twitter metadata fallbacks.
- Added a Cults3D model-page title fallback for pages blocked from server-side title retrieval.
- Added a Thingiverse profile-designs title fallback for rate-limited pages.
- Reversed public tab-name sorting to ascending order while keeping `hidefrompublic` ascending first.
- Author: GitHub Copilot

## 2026-09-18
- Added the single-file admin dashboard at `/admin/index.php` for adding, editing, listing, and removing records in the `tab`, `header`, and `links` tables.
- Added PDO prepared statements, CSRF protection, input validation, foreign-key selectors, and four-digit delete confirmation codes.
- Improved edit selection handling so invalid or missing records show a clear error instead of silently displaying the add form.
- Fixed strict-type rendering errors that prevented the edit form from displaying numeric record IDs.
- Added a clearer message when the database account lacks the UPDATE privilege required for editing.
- Added automatic page-name and favicon detection from the URL when adding a link; a missing favicon is ignored, while a missing page title prompts for a manual name.
- Author: GitHub Copilot
