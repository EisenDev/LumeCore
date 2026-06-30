# AGENTS.md - Development Rules & Guidelines

This document contains rules, styles, and architecture constraints for AI agents (Gemini, Claude, GPT, etc.) collaborating on LumeCore.

---

## 1. Development Principles

1. **Do Not Delete Backend Data/Integrations**:
   - When modifying UI layouts or refactoring dashboard panels, never remove the columns or data models from backend responses.
   - Always ensure fields like `score`, `sync_score`, and JSON columns in `VaultAsset` are fetched and handled.

2. **Zero Duplications**:
   - Do not duplicate modals like `ProjectForensicModal` or `DocumentReportModal`. Import them directly from `resources/js/Components/` and feed them snapshot data overlaying scan logs onto vault assets.

3. **No Hallucinated Classes/Icons**:
   - LumeCore uses **Tailwind CSS** along with a global custom layout in `resources/css/index.css`.
   - Never assume a dynamic utility class like arbitrary color variants or animation classes exist unless defined in `tailwind.config.js` or `index.css`.
   - Use standard SVG icons rather than referencing third-party icon libraries not installed in `package.json`.

---

## 2. Page & Component Development Rules

### Sidebar & Navigation (`Sidebar.vue`)
- The navigation items must be structured into grouped sections matching the mockup (`MAIN`, `MANAGE`).
- Keep the `Organizations` and `Billing & Usage` routes active.
- For nested routes requiring parameters (e.g. `team.index`), query the active organization via usePage:
  ```ts
  const page = usePage();
  const activeOrgId = computed(() => (page.props.auth as any).user?.active_organization_id);
  ```

### Modal Rendering Conditions
Ensure modals are only rendered when state variables are fully populated.
- Website/Project report: `<ProjectForensicModal v-if="selectedAsset" :show="..." :asset="selectedAsset" ... />`
- Sync report: `<WebURLandGitRepoSync v-if="selectedSyncWebAsset && selectedSyncRepoAsset" ... />`
- Repository report: `<GithubRepositoryForensicModal v-if="selectedRepoAsset" :show="..." :asset="selectedRepoAsset" ... />`

---

## 3. Database Modifications & Migrations
- Always write proper database migrations for any column additions.
- Do not bypass migration files by editing DB structures manually, as this will break container synchronization for developers.

---

## 4. Change Tracker
Record major workspace modifications here:

| Date       | Component Modified | Purpose                                                                 | Agent      |
|------------|--------------------|-------------------------------------------------------------------------|------------|
| 2026-06-23 | `Sidebar.vue`      | Refactored to structured categories, added scan integrity & analyst profile block | Antigravity|
| 2026-06-23 | `Scans.vue`        | Created page under `Pages/` with statistics row and dashboard filters  | Antigravity|
| 2026-06-23 | `web.php`          | Added route mapping for `/scans` pointing to `ScansController@index`     | Antigravity|
| 2026-06-23 | `ScansController`  | Added database fetching and stats aggregation for user scans           | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Centered radar chart, fixed label clipping, dynamic IP resolution, 3D perspective world map, and scaled text for readability | Antigravity|
| 2026-06-23 | `ProjectForensicModal.vue` | Deleted the old website scan results modal completely. | Antigravity|
| 2026-06-23 | `Dashboard.vue`    | Removed `ProjectForensicModal` references and routed clicks to new results page | Antigravity|
| 2026-06-23 | `Dashboard_remote.vue` | Cleaned up imports and markup tags for `ProjectForensicModal`. | Antigravity|
| 2026-06-23 | `tailwind.config.js` | Configured Inter and Plus Jakarta Sans globally as default Tailwind font family classes | Antigravity|
| 2026-06-23 | `app.css`          | Set default HTML/body and heading element fonts globally to align with landing page | Antigravity|
| 2026-06-23 | `app.blade.php`    | Replaced Figtree fonts bunny link with Google Fonts preconnect and import links for Inter & Plus Jakarta Sans | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Replaced 3D-tilted mini SVG map with full equirectangular world map (760×380), accurate continent shapes, marching-dash gold arc route from USA → target server (dynamic lat/lon via ip-api.com), triple-ring pulsing target pin, and redesigned Geo Location badge | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Full D3.js world map overhaul: Natural Earth projection via world-atlas TopoJSON, Geo badge moved to bottom-right, "View Map" button, full-screen modal with primary server pin (gold), external connection pins (CDN=cyan, analytics=purple, cloud=blue, payment=green, social=pink), surface-level security disclaimer | Antigravity|
| 2026-06-23 | WebsiteResults.vue | Replaced static background images with dynamic, offline-safe TopoJSON-based GeoJSON country paths. Mapped coordinates using standard D3 geoEquirectangular projections for pixel-perfect accuracy, styled the map to matches the gold-on-black mockup, and integrated zoom control handlers. | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Redesigned the SITE TOPOLOGY tab interface with 3-column layout, detailed cards, Lucide icons, responsive design, custom Sparkline SVG, and radial links layout. | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Redesigned the TECHNOLOGIES tab interface to match the exact 3-column layout of the topology tab, with animations, Lucide icons, and accurate styling. | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Made Evidence tab fully dynamic with category filters and search, implemented View All overlays, and removed the latency/integrity footer bar. | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Refactored Recommendations tab: replaced 4 hardcoded items + "Show more" button with a fully dynamic `v-for` over `filteredRecommendations`, scrollable container (`max-height: 560px`), filter pill counts driven by live data, per-item expand/collapse for "Why this matters" via `toggleRecommendation`, and severity-colored badges/impact values. | Antigravity|
| 2026-06-23 | `WebsiteResults.vue` | Rebuilt Recommendations tab to exactly match target mockup: colored-dot filter pills (All/Critical/High/Medium/Low), numbered severity squares, IMPACT/DIFFICULTY/TIME ESTIMATE column layout with clock icon, "Why this matters →" gold link, "Show N more" toggle button, right panel with gold gauge arc (32→60), Top Risk Areas with colored dots, and Implementation Notes with shield icon. | Antigravity|
| 2026-06-23 | `NewScanModal.vue` | Created new scanner modal component wrapping VaultUploader and UniversalScanning modals. | Antigravity|
| 2026-06-23 | `Scans.vue`        | Integrated NewScanModal and wired button click to open the modal overlay. | Antigravity|
| 2026-06-23 | `ScansController.php` | Exposed the wallet credits prop to Scans view. | Antigravity|
| 2026-06-23 | `Overview.vue`     | Refactored Dashboard page to Overview page matching mockup layout, fixed HTML tags, imported Link, and updated named routes. | Antigravity|
| 2026-06-23 | `web.php`          | Added route mapping for `/targets` pointing to `TargetsController@index` | Antigravity|
| 2026-06-23 | `TargetsController` | Created controller to fetch, group, and format synced targets and individual targets | Antigravity|
| 2026-06-23 | `Sidebar.vue`      | Updated Targets nav link to point to `targets.index` | Antigravity|
| 2026-06-23 | `Targets.vue`      | Created Targets dashboard page with metrics, grid/list filters, connection lines, and New Sync Target modal | Antigravity|
| 2026-06-23 | `web.php`, `TeamController.php`, `Sidebar.vue` | Added global `/teams` route mapping, resolved active organization redirection, and updated Teams sidebar link | Antigravity|
| 2026-06-24 | `Integrations.vue`, `Schedules.vue`, `Team.vue`, `AIAssistant.vue`, `Reports.vue` | Aligned title labels inside `<template #header>` slot and matched font/text size with the Scans page | Antigravity|
| 2026-06-24 | `Overview.vue`     | Removed "Export Overview" and timeframe date filter button controls from the page | Antigravity|
| 2026-06-29 | `.clickup.json`    | Verified and stored the project-to-ClickUp workspace/space/list mapping, and ran connection test | Antigravity|
| 2026-06-30 | `Reports.vue`, `ReportsController.php` | Created ClickUp ticket [DEBT-009](https://app.clickup.com/t/86d3gvxjz): Replace all hardcoded/mock data in Reports page with real DB-driven data via Inertia props from `scan_activities` and `vault_assets` | Antigravity|
| 2026-06-30 | `Reports.vue`, `ReportsController.php`, `ReportsTest.php` | Implemented dynamic backend mapping, SVG doughnut chart calculations, dropdown filters, and automated tests for [DEBT-009](https://app.clickup.com/t/86d3gw6a1) | Antigravity|
| 2026-06-30 | `AuthenticatedLayout.vue`, `HandleInertiaRequests.php`, `NotificationController.php`, `NotificationsTest.php` | Implemented notifications database table, shared props, mark-as-read endpoints, and interactive layout bell dropdown menu for [BUG-001](https://app.clickup.com/t/86d3gwcu5) | Antigravity|
| 2026-06-30 | `create_integrations_table.php`, `create_integration_logs_table.php` | Created `integrations` and `integration_logs` database migrations for [DEBT-010](https://app.clickup.com/t/86d3gx75d) | Antigravity|
| 2026-06-30 | `Integration.php`, `IntegrationLog.php` | Created Eloquent models with AES-256 encrypted credentials cast, scopeConnected, statusColor, lastSyncForHumans helpers for [DEBT-010](https://app.clickup.com/t/86d3gx75d) | Antigravity|
| 2026-06-30 | `IntegrationsController.php` | Rewrote controller to fetch real DB data, compute stats (connected, health %, events synced), build activity log, and added toggle endpoint for connect/disconnect for [DEBT-010](https://app.clickup.com/t/86d3gx75d) | Antigravity|
| 2026-06-30 | `Integrations.vue` | Removed all hardcoded arrays, added TypeScript defineProps interface, wired stats/health/activity log to Inertia props, wired connect/disconnect buttons to toggle endpoint for [DEBT-010](https://app.clickup.com/t/86d3gx75d) | Antigravity|
| 2026-06-30 | `web.php` | Added `POST /integrations/{platform}/toggle` route for [DEBT-010](https://app.clickup.com/t/86d3gx75d) | Antigravity|
| 2026-06-30 | `IntegrationsTest.php` | Added 12 feature tests covering auth gates, DB-driven props, connect/disconnect CRUD, credential security, and stats computation for [DEBT-010](https://app.clickup.com/t/86d3gx75d) | Antigravity|





