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
    <title>Parts Inventory | Technician Dashboard</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f8fafc;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .btn-adjust {
            background: #e2e8f0;
            color: #1e293b;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 1.2rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .btn-adjust:hover { background: #cbd5e1; }
        .btn-adjust.minus { color: #b91c1c; }
        .btn-adjust.plus { color: #15803d; }
        .qty-display {
            display: inline-block;
            width: 50px;
            text-align: center;
            font-weight: 800;
            font-size: 1.1rem;
        }
        .low-stock {
            color: #b91c1c;
            background: #fee2e2;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-left: 10px;
        }
    </style>
</head>
<body class="modern-theme" style="background-color: #f8fafc;">
    
    <div class="breadcrumb-container" role="banner" style="max-width: 1000px; margin: 20px auto; width: 95%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="#" class="crumb active">
                <span class="step-num">📦</span> Parts Inventory
            </a>
        </nav>
        <div>
            <span style="font-size: 0.9rem; color: #64748b; font-weight: 600; margin-right: 15px;">👤 <?= $user_display ?> (<?= $user_role ?>)</span>
            <a href="../../orders/core/logout.php" style="text-decoration: none; background: #fee2e2; color: #991b1b; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; border: 1px solid #fca5a5;">Sign Out</a>
        </div>
    </div>

    <main class="container" style="max-width: 1000px; margin: 0 auto; width: 95%;">
        
        <div class="page-header">
            <h1><span>📦</span> Parts Inventory</h1>
            <p>Manage and track stock levels for RAM, SSDs, batteries, and tools.</p>
        </div>

        <!-- Add New Part Form -->
        <div class="form-card">
            <form id="addPartForm" style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap;">
                <div style="flex: 2; min-width: 180px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px;">Part / Tool Name</label>
                    <input type="text" name="part_name" placeholder="e.g. 1TB NVMe SSD" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                </div>
                <div style="flex: 1; min-width: 130px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px;">Category</label>
                    <input type="text" name="category" placeholder="e.g. Storage" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                </div>
                <div style="flex: 0 0 90px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px;">Qty</label>
                    <input type="number" name="quantity" value="0" min="0" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                </div>
                <div style="flex: 0 0 90px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px;">Low Alert</label>
                    <input type="number" name="low_stock_threshold" value="5" min="0" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                </div>
                <button type="submit" style="background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 800; font-size: 0.95rem; cursor: pointer; white-space: nowrap;">+ Add Part</button>
            </form>
            <div id="addPartMessage" style="margin-top: 10px; font-weight: bold; text-align: center; font-size: 0.9rem;"></div>
        </div>

        <!-- Stock Table -->
        <div class="form-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h2 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Available Stock</h2>
                <button onclick="fetchInventory()" style="background: none; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: #475569;">🔄 Refresh</button>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Part / Tool Name</th>
                            <th>Category</th>
                            <th>Quantity In Stock</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <tr><td colspan="4" style="text-align: center; padding: 20px;">Loading inventory...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Add Part Form
        document.getElementById('addPartForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('addPartMessage');
            msg.textContent = 'Adding...';
            msg.style.color = '#475569';
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('../api/add_part.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    msg.textContent = '✅ Part added successfully!';
                    msg.style.color = '#15803d';
                    e.target.reset();
                    fetchInventory();
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

        async function fetchInventory() {
            const tbody = document.getElementById('inventoryTableBody');
            try {
                const response = await fetch('../api/get_inventory.php');
                const result = await response.json();
                
                if (result.success) {
                    if (result.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #64748b;">No parts found.</td></tr>';
                        return;
                    }
                    
                    let html = '';
                    result.data.forEach(part => {
                        const isLowStock = parseInt(part.quantity) <= parseInt(part.low_stock_threshold);
                        const lowStockBadge = isLowStock ? '<span class="low-stock">⚠️ LOW STOCK</span>' : '';
                        
                        html += `
                            <tr>
                                <td>
                                    <strong style="color: #1e293b; font-size: 1.05rem;">${part.part_name}</strong>
                                    ${lowStockBadge}
                                </td>
                                <td><span style="color: #64748b; font-size: 0.85rem; text-transform: uppercase; font-weight: 700;">${part.category}</span></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <button class="btn-adjust minus" onclick="adjustPart(${part.id}, -1)">-</button>
                                        <span class="qty-display" style="${isLowStock ? 'color: #b91c1c;' : ''}">${part.quantity}</span>
                                        <button class="btn-adjust plus" onclick="adjustPart(${part.id}, 1)">+</button>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <button onclick="deletePart(${part.id}, '${part.part_name.replace(/'/g, "\\'")}')" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem;" title="Remove Part">🗑️</button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #ef4444;">Failed to load inventory.</td></tr>';
            }
        }

        async function adjustPart(id, adjustment) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('adjustment', adjustment);
                
                const response = await fetch('../api/update_part.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    fetchInventory();
                } else {
                    alert('Failed to update: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        async function deletePart(id, name) {
            if (!confirm('Are you sure you want to remove "' + name + '" from the inventory?')) return;
            
            try {
                const formData = new FormData();
                formData.append('id', id);
                
                const response = await fetch('../api/delete_part.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    fetchInventory();
                } else {
                    alert('Failed to delete: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        // Initial fetch
        fetchInventory();
    </script>
</body>
</html>
