# 📦 IQA Warehouse Systems

[![Version](https://img.shields.io/badge/version-2.0.0-green.svg)](https://github.com/)
[![Tech](https://img.shields.io/badge/Stack-Vanilla_PHP_|_SQLite_|_JS-blue.svg)](https://github.com/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/)

A premium, high-performance warehouse management ecosystem designed for speed, reliability, and precision. Built for physical warehouse environments where quick hardware intake and accurate label logistics are mission-critical.

---

## 🚀 The Modules

### 🏷️ Inventory Labels (`/labels`)
*Rapid Hardware Intake & ODT Generation*
- **Speed Intake**: Optimized forms for rapid technical specs entry.
- **Thermal Printing**: Generates high-fidelity `.odt` labels via a dependency-free Flat XML engine.
- **Hardware Specs**: Detailed tracking of CPUs, RAM, Storage, Battery Health, and BIOS status.
- **Self-Healing**: Native `Schema Guard` ensures database integrity and automatic recovery.

### 📊 Order Manager (`/orders`)
*B2B Relationship & Batch Fulfillment*
- **CRM Hub**: Advanced lead tracking with interaction timelines and status priority.
- **Batch Logistics**: Manage complex hardware orders with real-time stock allocation.
- **Warehouse Gates**: Track the operational state of physical zones (Working, Audit, Idle).
- **Global Registry**: Searchable customer database with session-persistent filters.

---

## 🛠️ Technology Stack

| Layer | Tech | Description |
| :--- | :--- | :--- |
| **Backend** | PHP 8.1+ | Lean, procedural-focused logic with modular routing. |
| **Database** | SQLite 3 | Zero-config, portable database files with optimistic locking. |
| **Frontend** | Vanilla JS / CSS3 | Modern "App-like" experience using Glassmorphism & HSL variables. |
| **Documents** | Flat XML (FODT) | Dependency-free OpenDocument generation for LibreOffice compatibility. |
| **Automation** | PowerShell | Native Windows integration for direct file launching. |

---

## 📂 Project Structure

```text
├── labels/                # Module: Inventory & Rapid Label Printing
├── orders/                # Module: CRM, Batching & Fulfillment
├── DOCS/                  # System-wide Documentation
│   ├── AI_AGENT_INSTRUCTIONS.md   # Guidelines for AI coding assistants
│   ├── CODE_REVIEW_CHECKLIST.md   # Quality control standards
│   └── GLOBAL_SITEMAP.md          # Full project directory map
├── index.php              # Premium Portal / Landing Page
└── README.md              # This document
```

---

## ⚙️ Getting Started

### 1. Requirements
- **PHP 8.1+** (XAMPP / WAMP recommended for Windows environments).
- **SQLite3 Extension** enabled in `php.ini`.
- **LibreOffice** (optional, for viewing/printing generated `.odt` labels).

### 2. Installation
1. Clone the repository into your web root (e.g., `htdocs/app`).
2. Ensure the `/db` and `/assets/db` directories have **Write Permissions**.
3. Access the system via `http://localhost/app/`.

### 3. Usage
- Start in the **Portal** to choose between label generation or order management.
- Databases are automatically initialized on the first run via the **Schema Guard** system.

---

## 🔍 Documentation for Reviewers
If you are an AI assistant or a human code reviewer, please consult the following:
- [🤖 AI Agent Instructions](DOCS/AI_AGENT_INSTRUCTIONS.md)
- [🔍 Reviewer Checklist](DOCS/CODE_REVIEW_CHECKLIST.md)
- [🗺️ Global Sitemap](DOCS/GLOBAL_SITEMAP.md)

---

> [!TIP]
> Built for durability. Every interaction is audited, every database is self-healing, and every UI element is touch-optimized for warehouse hardware.

&copy; 2026 IQA Metal Inventory Systems
