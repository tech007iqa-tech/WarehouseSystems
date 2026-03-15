# Local Environment Setup Guide (XAMPP)

## 1. Overview
The IQA Metal Label & Inventory system is designed to run on a local network server. This guide explains how to set up the environment on a dedicated Windows machine using **XAMPP**.

By following these steps, any device on your local warehouse network (phones, tablets, other laptops) will be able to access the app via a web browser.

---

## 2. XAMPP Installation & Configuration
XAMPP provides the Apache web server and PHP environment needed for this app.

1. **Download & Install:** Download XAMPP for Windows (PHP 8.0+ recommended).
2. **Install Location:** Leave it at the default directory (`C:\xampp`).
3. **Start Services:** Open the XAMPP Control Panel and start the **Apache** service. (You do not need to start MySQL, as we use SQLite).

---

## 3. Configuring PHP for SQLite (`php.ini`)
SQLite does not require a background service, but PHP needs permission to talk to the `.sqlite` files.

1. In the XAMPP Control Panel, click the **Config** button next to Apache and select `PHP (php.ini)`.
2. A text editor will open. Search for the following lines:
   ```ini
   ;extension=pdo_sqlite
   ;extension=sqlite3
   ```
3. **Remove the preceding semicolon (`;`)** to uncomment and enable them:
   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```
4. Save the file and **Restart Apache** from the XAMPP Control Panel.

---

## 4. Enabling PowerShell Script Execution
Because this app generates `.odt` and `.ots` files natively using PowerShell (avoiding bloated PHP packages), the Windows server machine must allow scripts to run.

1. On the Windows Server machine, click Start and search for **PowerShell**.
2. Right-click it and select **Run as Administrator**.
3. Run the following command to check your current policy:
   ```powershell
   Get-ExecutionPolicy
   ```
   *If it says `Restricted`, PowerShell will block our template files from generating.*
4. Change the policy by running:
   ```powershell
   Set-ExecutionPolicy RemoteSigned
   ```
   *(Press `Y` to confirm when prompted).*

---

## 5. Deploying the Application Code
1. Open the XAMPP web root directory: `C:\xampp\htdocs\`
2. Create a new folder for the app: `C:\xampp\htdocs\LabelAPP\`
3. Copy all the files from this repository into that folder.
4. Ensure the `/db/`, `/exports/`, and `/templates/` directories have read/write permissions so PHP isn't blocked from modifying the SQLite files or generating new labels.

---

## 6. Accessing the App on the Local Network
**On the Host Server Machine:**
Open a web browser and go to: `http://localhost/LabelAPP/`

**From Other Devices on the Warehouse Network:**
1. On the host server machine, open Command Prompt (`cmd`) and type `ipconfig`.
2. Look for the **IPv4 Address** (e.g., `192.168.1.50`).
3. On any phone or laptop connected to the same WiFi/Network, open the browser and go to: `http://192.168.1.50/LabelAPP/`
