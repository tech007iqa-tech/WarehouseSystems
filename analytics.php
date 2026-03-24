<?php
/**
 * analytics.php
 * System-wide performance and inventory insights.
 */
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
?>

<div class="panel" style="margin-bottom: 25px;">
    <h1 style="font-size: 1.5rem; margin-bottom: 5px;">📊 Performance & Logistics</h1>
    <p style="color: var(--text-secondary); font-size: 0.9rem;">Real-time insights on inventory stock, aging, and sales velocity.</p>
</div>

<!-- TOP SUMMARY CARDS -->
<div class="stat-grid" style="margin-bottom: 30px;" id="summaryCards">
    <div class="panel text-center">
        <span class="stat-label">Stock Units</span>
        <p class="stat-value" id="total_stock">—</p>
        <span class="stat-subtext" style="color: var(--accent-color);">In Warehouse</span>
    </div>
    <div class="panel text-center">
        <span class="stat-label">Month Sales</span>
        <p class="stat-value" id="this_month_sales">—</p>
        <span class="stat-subtext">Current Period</span>
    </div>
    <div class="panel text-center">
        <span class="stat-label">Ready to Dispatch</span>
        <p class="stat-value" id="sold_stock" style="color: #f39c12;">—</p>
        <span class="stat-subtext">Sold Inventory</span>
    </div>
    <div class="panel text-center">
        <span class="stat-label">Avg Hold Time</span>
        <p class="stat-value" id="avg_stock_days">—</p>
        <span class="stat-subtext">Days in Warehouse</span>
    </div>
</div>

<div class="grid-2-1" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    
    <!-- BRAND DISTRIBUTION (Horizontal Bar Chart) -->
    <div class="panel">
        <h3 class="form-section-header">Top 5 Brands in Stock</h3>
        <div id="brandChart" class="css-chart-container">
            <!-- Dynamic Injection -->
        </div>
    </div>

    <!-- SALES VELOCITY -->
    <div class="panel">
        <h3 class="form-section-header">Last 6 Months Sales</h3>
        <div id="salesChart" class="css-chart-container vertical">
            <!-- Dynamic Injection -->
        </div>
    </div>

    <!-- TIER DISTRIBUTION -->
    <div class="panel">
        <h3 class="form-section-header">Sales by Pricing Tier</h3>
        <div id="tierChart" class="css-chart-container">
            <!-- Dynamic Injection -->
        </div>
    </div>

    <!-- TOP BUYERS -->
    <div class="panel">
        <h3 class="form-section-header">Top 3 B2B Buyers</h3>
        <div id="buyersChart" class="css-chart-container">
            <!-- Dynamic Injection -->
        </div>
    </div>

    <!-- INVENTORY AGING (DANGER LIST) -->
    <div class="panel" style="grid-column: 1 / -1;">
        <h3 class="form-section-header" style="color: #ef4444;">🚨 Inventory Aging - Oldest Stock Items</h3>
        <div class="table-responsive">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Hardware</th>
                        <th>Condition</th>
                        <th>Stock Date</th>
                        <th style="text-align: right;">Days in Warehouse</th>
                    </tr>
                </thead>
                <tbody id="agingBody">
                    <!-- Dynamic Injection -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* --- ANALYTICS SPECIFIC STYLES --- */
.stat-label { font-size: 0.7rem; text-transform: uppercase; font-weight: 800; color: var(--text-secondary); }
.stat-value { font-size: 1.8rem; font-weight: 800; margin: 5px 0; color: var(--text-main); }
.stat-subtext { font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); }

.css-chart-container { display: flex; flex-direction: column; gap: 15px; margin-top: 10px; }
.chart-row { display: grid; grid-template-columns: 80px 1fr 40px; align-items: center; gap: 10px; }
.chart-bar-bg { height: 12px; background: rgba(0,0,0,0.05); border-radius: 6px; overflow: hidden; position: relative; }
.chart-bar-fill { height: 100%; background: var(--accent-color); border-radius: 6px; transition: width 0.8s cubic-bezier(0.1, 0.7, 1.0, 0.1); }
.chart-label { font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); white-space: nowrap; }
.chart-value { font-size: 0.8rem; font-weight: 800; text-align: right; }

/* Vertical Bar Chart for Sales */
.css-chart-container.vertical { flex-direction: row; align-items: flex-end; justify-content: space-around; height: 150px; padding-bottom: 25px; }
.chart-col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; flex: 1; gap: 10px; }
.chart-vbar-bg { width: 30px; background: rgba(0,0,0,0.05); border-radius: 4px; position: relative; height: 100%; display: flex; align-items: flex-end; }
.chart-vbar-fill { width: 100%; background: var(--accent-color); border-radius: 4px; transition: height 0.8s ease-out; }
.chart-vlabel { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); position: absolute; bottom: -22px; width: 60px; text-align: center; white-space: nowrap; transform: rotate(-15deg); }

.aging-danger { color: #ef4444; font-weight: 800; }
</style>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    // UI: Set active state
    document.getElementById('nav-analytics').classList.add('active');

    try {
        const res = await fetch('api/get_analytics.php');
        const json = await res.json();

        if (!json.success) {
            console.error('Analytics failed', json.error);
            return;
        }

        const d = json.data;

        // 1. Summary
        document.getElementById('total_stock').innerText = d.summary.total_stock;
        document.getElementById('sold_stock').innerText  = d.summary.sold_stock;
        document.getElementById('this_month_sales').innerText = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(d.summary.this_month_sales);
        document.getElementById('avg_stock_days').innerText = d.summary.avg_stock_days + ' Days';

        // Helper for simple bar charts
        const renderBarChart = (id, data, formatAsCurrency = false) => {
            const container = document.getElementById(id);
            if (!data || data.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:var(--text-secondary);">No data yet.</p>';
                return;
            }
            const max = Math.max(...data.map(i => i.count));
            container.innerHTML = data.map(i => `
                <div class="chart-row">
                    <span class="chart-label" title="${i.label}">${i.label}</span>
                    <div class="chart-bar-bg"><div class="chart-bar-fill" style="width: 0%" data-w="${(i.count / max * 100).toFixed(0)}%"></div></div>
                    <span class="chart-value">${formatAsCurrency ? '$' + Math.round(i.count).toLocaleString() : i.count}</span>
                </div>
            `).join('');
        };

        // 2. Row Charts
        renderBarChart('brandChart', d.brand_distribution.slice(0, 5));
        renderBarChart('tierChart', d.tier_distribution, true);
        renderBarChart('buyersChart', d.top_buyers, true);

        // 3. Sales Chart (Vertical)
        const salesChart = document.getElementById('salesChart');
        const sales = d.sales_performance;
        if (sales && sales.length > 0) {
            const maxRev = Math.max(...sales.map(s => s.total_revenue));
            salesChart.innerHTML = sales.map(s => `
                <div class="chart-col">
                    <div class="chart-vbar-bg" title="$${s.total_revenue.toLocaleString()}"><div class="chart-vbar-fill" style="height: 0%" data-h="${(s.total_revenue / maxRev * 100).toFixed(0)}%"></div></div>
                    <span class="chart-vlabel">${s.month}</span>
                </div>
            `).join('');
        }

        // 4. Aging Table
        const agingBody = document.getElementById('agingBody');
        agingBody.innerHTML = d.aging_inventory.map(item => {
            const daysClass = item.days_old > 30 ? 'aging-danger' : '';
            return `
                <tr>
                    <td><strong>#${item.id.toString().padStart(5,'0')}</strong> ${item.brand} ${item.model}</td>
                    <td>${item.description}</td>
                    <td style="font-size: 0.8rem;">${item.created_at.substring(0,10)}</td>
                    <td style="text-align: right;" class="${daysClass}">${Math.round(item.days_old)} Days</td>
                </tr>
            `;
        }).join('');

        // TRIGGER ANIMATIONS
        setTimeout(() => {
            document.querySelectorAll('.chart-bar-fill').forEach(el => el.style.width = el.dataset.w);
            document.querySelectorAll('.chart-vbar-fill').forEach(el => el.style.height = el.dataset.h);
        }, 100);

    } catch (e) {
        console.error(e);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
