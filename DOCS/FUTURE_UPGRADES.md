# 🚀 AI Future Upgrades & Potential Enhancements

**File:** `DOCS/FUTURE_UPGRADES.md`
**Purpose:** A brain-dump of brainstormed features, potential architectural upgrades, and UX enhancements that AI agents can use for inspiration when moving the IQA Metal project forward.

---

## 1. 🚚 Sales & Dispatch Workflow (Advanced)
*Currently, "Sold" items are managed through status updates. A future upgrade could entail:*
- **Dedicated Dispatch UI:** A separate page specifically for managing sold inventory waiting to be shipped.
- **Automated Archive:** Automatically offload "Sold" items from the primary `labels.sqlite` view into an `archive.sqlite` or a locked state after a configurable number of days, keeping the main inventory view fast and clean.
- **Integration with Orders:** When a `.ots` Purchase Order is generated, automatically prompt the user to transition the status of included items to "Sold" or "Reserved".

## 2. 🖨️ Thermal Printer Native Optimization
*Expanding the current ODT label generation for specialized warehouse hardware.*
- **Direct ZPL/EPL Integration:** Bypass LibreOffice entirely for label printing and generate native Zebra/Epson thermal printer commands.
- **Margin-less 4x6 Templates:** Create specific layout templates optimized for high-speed 4x6 shipping/inventory labels.

## 3. 🧠 Smart Diagnostics & Triage
- **Automated Hardware Profiling:** Build a lightweight PowerShell agent that can run on a client machine, gather its CPU, RAM, and Storage automatically, and post that JSON directly into `new_label.php` to completely eliminate manual intake typing.
- **Battery Health Analytics:** Track and chart battery degradation over time across specific laptop models.

## 4. 📇 Advanced CRM & B2B Purchasing
- **Customer Portals:** A lightweight read-only view where repeating B2B customers can view available inventory and request a formalized Order Form natively.
- **Tiered Pricing Rules:** Automatic application of discounts on hardware based on the selected customer profile in `new_order.php`.

## 5. 📱 Progressive Web App (PWA) Evolution
- **Offline Mode:** Allow floor technicians to clone profiles, edit forms, and intake hardware using their mobile device even when not directly connected to the local XAMPP server, syncing when they reconnect.
- **Barcode/QR Code Scanning:** Integrate a browser-based camera reader (e.g., HTML5-QRCode) so technicians can scan physical labels on hardware to instantly jump to its `hardware_view.php` sheet. 
