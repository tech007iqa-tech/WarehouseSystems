# 🚀 AI Future Upgrades & Potential Enhancements

**File:** `DOCS/FUTURE_UPGRADES.md`
**Purpose:** A brain-dump of brainstormed features, potential architectural upgrades, and UX enhancements that AI agents can use for inspiration when moving the IQA Metal project forward.

---

## 1. 🚚 Sales & Dispatch Workflow (Advanced)
*Currently, "Sold" items are managed through status updates. A future upgrade could entail:*
- **Dedicated Dispatch UI:** A separate page specifically for managing sold inventory waiting to be shipped.
- **Automated Archive:** Automatically offload "Sold" items from the primary `labels.sqlite` view into an `archive.sqlite` or a locked state after a configurable number of days, keeping the main inventory view fast and clean.
- **Integration with Orders:** When a `.ots` Purchase Order is generated, automatically prompt the user to transition the status of included items to "Sold" or "Reserved".

## 2. 📇 Advanced CRM & B2B Purchasing
- **Customer Portals:** A lightweight read-only view where repeating B2B customers can view available inventory and request a formalized Order Form natively.
- **Tiered Pricing Rules:** Automatic application of discounts on hardware based on the selected customer profile in `new_order.php`.

---

### ✅ Completed / Integrated
- **Thermal Printer Native Optimization:** (DONE: Phase 9) - Unified high-speed 2x1 HTML/CSS printing implemented.
- **Offline / Zero-Storage Mode:** (DONE: Phase 9) - Browser-native printing bypasses file system storage for rapid warehouse use.
- **Visual Identification Hooks:** (DONE: Phase 10) - Hardware ID anchors added to physical labels to prepare for future manual scanning.
