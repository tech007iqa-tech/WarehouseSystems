<?php
require_once '../core/database.php';
require_once '../core/auth.php';

$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Logs | Technician Dashboard</title>
    <!-- Include Orders Design System -->
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../../orders/assets/styles/dashboard.css">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
    <style>
        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header p {
            margin: 0;
            color: #94a3b8;
            font-size: 1rem;
        }
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }
        .form-control {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group.span-2 {
            grid-column: span 2;
        }
        .status-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .status-btn {
            padding: 10px 28px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            color: #475569;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .status-btn.active.good {
            background: #f0fdf4;
            border-color: #22c55e;
            color: #15803d;
        }
        .status-btn.active.bad {
            background: #fef2f2;
            border-color: #ef4444;
            color: #b91c1c;
        }
        .form-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn-submit {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #4338ca;
        }
        
        .log-table-container {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f8fafc;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .badge.good { background: #dcfce7; color: #166534; }
        .badge.bad { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="modern-theme" style="background-color: #f8fafc;">
    
    <div class="breadcrumb-container" role="banner" style="max-width: 1200px; margin: 20px auto; width: 95%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="#" class="crumb active">
                <span class="step-num">📋</span> Hardware Logs
            </a>
        </nav>
        <div>
            <span style="font-size: 0.9rem; color: #64748b; font-weight: 600; margin-right: 15px;">👤 <?= $user_display ?> (<?= $user_role ?>)</span>
            <a href="../../orders/core/logout.php" style="text-decoration: none; background: #fee2e2; color: #991b1b; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; border: 1px solid #fca5a5;">Sign Out</a>
        </div>
    </div>

    <main class="container" style="max-width: 1200px; margin: 0 auto; width: 95%;">
        
        <div class="page-header">
            <h1><span>📋</span> Hardware Logs</h1>
            <p>Log your daily hardware tests. Select Good or Bad to classify the unit.</p>
        </div>

        <!-- New Log Entry Form (horizontal, full width) -->
        <div class="form-card">
            <form id="logForm">
                <div class="form-top-row">
                    <h2 style="margin: 0; font-size: 1.2rem; color: #1e293b;">New Log Entry</h2>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="status-toggle" style="margin-bottom: 0;">
                            <button type="button" class="status-btn active good" onclick="setStatus('Good')">✅ GOOD</button>
                            <button type="button" class="status-btn bad" onclick="setStatus('Bad')">❌ BAD</button>
                            <input type="hidden" id="status" name="status" value="Good">
                        </div>
                        <button type="submit" class="btn-submit">Save Log Entry</button>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>QTY</label>
                        <input type="number" class="form-control" name="qty" value="1" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Make</label>
                        <input type="text" class="form-control" name="make" placeholder="e.g. Dell" required>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" class="form-control" name="model" placeholder="e.g. Latitude 7490" required>
                    </div>
                    <div class="form-group">
                        <label>Series</label>
                        <input type="text" class="form-control" name="series" placeholder="e.g. 7000">
                    </div>
                    <div class="form-group">
                        <label>CPU</label>
                        <input type="text" class="form-control" name="cpu" placeholder="e.g. i5-8350U">
                    </div>
                    <div class="form-group">
                        <label>GPU</label>
                        <input type="text" class="form-control" name="gpu" placeholder="e.g. Intel UHD">
                    </div>
                    <div class="form-group">
                        <label>RAM</label>
                        <input type="text" class="form-control" name="ram" placeholder="e.g. 16GB">
                    </div>
                    <div class="form-group">
                        <label>Storage</label>
                        <input type="text" class="form-control" name="storage" placeholder="e.g. 256GB SSD">
                    </div>
                    <div class="form-group">
                        <label>Battery</label>
                        <input type="text" class="form-control" name="battery" placeholder="e.g. Excellent">
                    </div>
                    <div class="form-group">
                        <label>BIOS State</label>
                        <input type="text" class="form-control" name="bios_state" placeholder="e.g. Unlocked">
                    </div>
                    <div class="form-group span-2">
                        <label>Notes</label>
                        <input type="text" class="form-control" name="notes" placeholder="Any additional findings...">
                    </div>
                </div>
                <div id="formMessage" style="font-weight: bold; text-align: center; font-size: 0.9rem;"></div>
            </form>
        </div>

        <!-- Recent Logs Table (full width, below) -->
        <div class="form-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h2 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Recent Logs</h2>
                <button onclick="fetchLogs()" style="background: none; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: #475569;">🔄 Refresh</button>
            </div>
            <div class="log-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Make / Model</th>
                            <th>Specs</th>
                            <th>Tech</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <tr><td colspan="5" style="text-align: center; padding: 20px;">Loading logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        function setStatus(status) {
            document.getElementById('status').value = status;
            const btns = document.querySelectorAll('.status-btn');
            btns.forEach(btn => btn.classList.remove('active'));
            
            if (status === 'Good') {
                btns[0].classList.add('active');
            } else {
                btns[1].classList.add('active');
            }
        }

        document.getElementById('logForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('formMessage');
            msg.textContent = 'Saving...';
            msg.style.color = '#475569';
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('../api/add_log.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    msg.textContent = '✅ Log saved successfully!';
                    msg.style.color = '#15803d';
                    e.target.reset();
                    fetchLogs();
                    
                    setTimeout(() => { msg.textContent = ''; }, 3000);
                } else {
                    msg.textContent = '❌ Error: ' + result.error;
                    msg.style.color = '#b91c1c';
                }
            } catch (err) {
                msg.textContent = '❌ Network Error';
                msg.style.color = '#b91c1c';
            }
        });

        async function fetchLogs() {
            const tbody = document.getElementById('logsTableBody');
            try {
                const response = await fetch('../api/get_logs.php');
                const result = await response.json();
                
                if (result.success) {
                    if (result.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #64748b;">No logs found for today.</td></tr>';
                        return;
                    }
                    
                    let html = '';
                    result.data.forEach(log => {
                        const badgeClass = log.status === 'Good' ? 'good' : 'bad';
                        let actionHtml = `<small style="color: #64748b;">${new Date(log.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</small>`;
                        
                        if (log.status === 'Good' && parseInt(log.ready_for_warehouse) === 0) {
                            actionHtml = `<button onclick="passToWarehouse(${log.id})" style="background: #4f46e5; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">Send to Warehouse</button>`;
                        } else if (log.status === 'Good' && parseInt(log.ready_for_warehouse) === 1) {
                            actionHtml = `<span style="color: #166534; font-size: 0.8rem; font-weight: 700;">📦 Ready</span>`;
                        }

                        html += `
                            <tr>
                                <td><span class="badge ${badgeClass}">${log.status}</span></td>
                                <td><strong>${log.make} ${log.model}</strong><br><small style="color: #64748b;">Qty: ${log.qty}</small></td>
                                <td><small>${log.cpu || '-'} / ${log.ram || '-'} / ${log.storage || '-'}</small></td>
                                <td><small>${log.tech_id}</small></td>
                                <td style="text-align: right;">${actionHtml}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #ef4444;">Failed to load logs.</td></tr>';
            }
        }

        async function passToWarehouse(id) {
            if(!confirm('Mark this unit as ready for the main warehouse?')) return;
            
            try {
                const formData = new FormData();
                formData.append('id', id);
                
                const response = await fetch('../api/pass_warehouse.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    fetchLogs();
                } else {
                    alert('Failed: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        // Initial fetch
        fetchLogs();
    </script>
</body>
</html>
