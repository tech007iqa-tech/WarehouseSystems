# 🤖 AI Agent Instructions & Architecture Guidelines

## 🎯 Overview
This document is for future AI coding assistants working on the **IQA Warehouse Systems**. This project is a lean, high-performance ecosystem designed for speed and reliability in a physical warehouse environment.

---

## 🛠️ Tech Stack & Philosophy
1.  **No Frameworks**: We avoid heavy PHP frameworks or Node.js build steps. Stick to **Vanilla PHP 8+**, **Vanilla JS**, and **Vanilla CSS**.
2.  **SQLite First**: Data is stored in modular `.sqlite` files. Use `includes/db.php` (Labels) or `core/database.php` (Orders) for connections.
3.  **Schema Guard**: Always maintain the "Self-Healing" pattern. If you add a column, update the `schema_guard.php` (Labels) or the database initialization logic (Orders).
4.  **Flat XML (FODT)**: Do NOT use ZipArchive for label generation. Generate Flat XML ODT files as they are portable and dependency-free.
5.  **Premium UI**: Every UI element must feel premium. Use HSL colors, glassmorphism, smooth transitions, and high contrast for warehouse visibility.

---

## 🏗️ Structural Rules
- **Absolute Paths**: Always use `__DIR__` for PHP requires to ensure portability across XAMPP environments.
- **Audit Logging**: Every mutation (Insert/Update/Delete) MUST trigger an audit entry. Use the `log_audit_event()` helper.
- **Technical Fingerprinting**: Before adding new hardware, check for exact technical duplicates to prevent inventory clutter.

---

## 🚨 Critical Constraints
- **PowerShell Integration**: The Label system uses PowerShell for direct file launching on Windows hosts. Maintain `api/open_windows_file.php` compatibility.
- **iOS Safari Optimization**: Many warehouse devices are iPads/iPhones. Ensure all CSS uses `48px` touch targets and avoids `:hover` dependent logic for critical actions.
- **No Global JS Variables**: Scope all JS within modules or `DOMContentLoaded` listeners to prevent collisions.

---

## 🧪 Testing Protocol
1.  **DB Check**: After any schema change, delete a test DB and let the `Schema Guard` rebuild it.
2.  **ODT Validation**: Verify generated `.odt` files open in LibreOffice without "Corrupt File" warnings (check XML well-formedness).
3.  **Responsive Audit**: Test in a mobile-width browser to ensure the Sidebar and Action Grids don't break.
