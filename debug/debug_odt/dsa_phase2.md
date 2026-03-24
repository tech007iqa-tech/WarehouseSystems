# 🏗️ Implementation Plan: Phase 2 - Structural Integrity & UI Componentization

## 🎯 Goal
Decouple HTML structure from JavaScript logic using browser-native `<template>` tags. This ensures that UI changes can be made in HTML files with full IDE support, while JavaScript remains focused on data binding and event handling.

## 🧱 1. Two-Factor Template Strategy

### A. Display Template: `#inventoryRowTemplate`
*   Move the `tr` structure from `buildRow()` in `labels.js` to `labels.php`.
*   Use data-attributes or classes for targeted data injection.

### B. Edit Template: `#editRowTemplate`
*   Move the inline edit form structure from `openEditRow()` to `labels.php`.
*   Ensure all mapped field names from `HW_FIELDS` are dynamically set.

---

## 🚀 2. Migration Steps

### Step 1: Initialize Templates ([labels.php](file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/labels.php))
*   Add `<template>` tags at the bottom of the file (before the footer).
*   Clean up the duplicate PHP logic in `labels.php` found during inspection.

### Step 2: UI Engine Refactor ([assets/js/labels.js](file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/assets/js/labels.js))
*   Update `buildRow()` to clone `#inventoryRowTemplate` instead of using `tr.innerHTML`.
*   Update `openEditRow()` to clone `#editRowTemplate`.
*   Replace manual string escaping/concatenation with `querySelector` updates.

### Step 3: Verification
*   Test searching, filtering, and inline editing.
*   Ensure that no data loss occurs during the edit/save cycle.

---

## ⚠️ 3. Safety Standards
1.  **NO HTML IN JS:** Future agents are forbidden from adding new HTML strings to JS files. They must update the template in the `.php` file instead.
2.  **DOM Target Stability:** Use specific classes (e.g., `.tpl-brand`) for data injection instead of brittle index-based `td` selection.
