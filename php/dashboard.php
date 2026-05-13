<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin();

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$kpi_stmt = $mysqli->prepare(
    "SELECT COUNT(*) as total_leaves,
            SUM(is_archived = 1) as archived_leaves,
            COUNT(DISTINCT employee_name) as employee_count
     FROM leave_data
     WHERE YEAR(leave_date) = ?"
);
$kpi_stmt->bind_param("i", $selected_year);
$kpi_stmt->execute();
$kpi = $kpi_stmt->get_result()->fetch_assoc() ?: [];
$kpi_stmt->close();

$monthly_stmt = $mysqli->prepare(
    "SELECT MONTH(leave_date) as month_num, COUNT(*) as leave_count
     FROM leave_data
     WHERE YEAR(leave_date) = ?
     GROUP BY MONTH(leave_date)
     ORDER BY MONTH(leave_date)"
);
$monthly_stmt->bind_param("i", $selected_year);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();
$monthly_data = array_fill(1, 12, 0);
while ($row = $monthly_result->fetch_assoc()) {
    $month_num = (int)$row['month_num'];
    $monthly_data[$month_num] = (int)$row['leave_count'];
}
$monthly_stmt->close();

$type_stmt = $mysqli->prepare(
    "SELECT leave_type, COUNT(*) as leave_count
     FROM leave_data
     WHERE YEAR(leave_date) = ?
     GROUP BY leave_type
     ORDER BY leave_count DESC"
);
$type_stmt->bind_param("i", $selected_year);
$type_stmt->execute();
$type_result = $type_stmt->get_result();
$type_labels = [];
$type_counts = [];
while ($row = $type_result->fetch_assoc()) {
    $type_labels[] = $row['leave_type'];
    $type_counts[] = (int)$row['leave_count'];
}
$type_stmt->close();

$top_stmt = $mysqli->prepare(
    "SELECT employee_name, COUNT(*) as leave_count
     FROM leave_data
     WHERE YEAR(leave_date) = ?
     GROUP BY employee_name
     ORDER BY leave_count DESC, employee_name ASC
     LIMIT 30"
);
$top_stmt->bind_param("i", $selected_year);
$top_stmt->execute();
$top_result = $top_stmt->get_result();
$top_employees = [];
while ($row = $top_result->fetch_assoc()) {
    $top_employees[] = $row;
}
$top_stmt->close();

$recent_stmt = $mysqli->prepare(
    "SELECT employee_name, leave_date, leave_type, is_archived
     FROM leave_data
     WHERE YEAR(leave_date) = ?
     ORDER BY leave_date DESC, id DESC"
);
$recent_stmt->bind_param("i", $selected_year);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
$recent_records = [];
while ($row = $recent_result->fetch_assoc()) {
    $recent_records[] = $row;
}
$recent_stmt->close();

$emp_monthly_stmt = $mysqli->prepare(
    "SELECT MONTH(leave_date) as month_num, COUNT(DISTINCT employee_name) as emp_count
     FROM leave_data
     WHERE YEAR(leave_date) = ?
     GROUP BY MONTH(leave_date)
     ORDER BY MONTH(leave_date)"
);
$emp_monthly_stmt->bind_param("i", $selected_year);
$emp_monthly_stmt->execute();
$emp_monthly_result = $emp_monthly_stmt->get_result();
$emp_monthly_data = array_fill(1, 12, 0);
while ($row = $emp_monthly_result->fetch_assoc()) {
    $emp_monthly_data[(int)$row['month_num']] = (int)$row['emp_count'];
}
$emp_monthly_stmt->close();
$emp_month_counts = array_values($emp_monthly_data);

$months = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];

$month_labels = $months;
$month_counts = array_values($monthly_data);
$archive_count = (int)($kpi['archived_leaves'] ?? 0);
$active_count = max(0, (int)($kpi['total_leaves'] ?? 0) - $archive_count);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Attendance Leave Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
            align-items: stretch;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 10px 24px rgba(13, 27, 75, 0.08);
            border: 1px solid #eef1f5;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 6px;
            font-weight: 600;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--navy);
        }
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }
        .chart-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #eef1f5;
            box-shadow: 0 10px 24px rgba(13, 27, 75, 0.08);
        }
        .chart-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 10px;
        }
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .search-input {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            min-width: 220px;
        }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pagination-info {
            font-size: 13px;
            color: var(--text-muted, #666);
        }
        .pagination-controls {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .page-btn {
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 8px;
            border: 1px solid var(--border, #dde1ea);
            background: #fff;
            color: var(--navy, #1a2e5a);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .page-btn:hover:not(:disabled) {
            background: var(--navy, #1a2e5a);
            color: #fff;
            border-color: var(--navy, #1a2e5a);
        }
        .page-btn.active {
            background: var(--navy, #1a2e5a);
            color: #fff;
            border-color: var(--navy, #1a2e5a);
        }
        .page-btn:disabled {
            opacity: 0.38;
            cursor: not-allowed;
        }
        .page-ellipsis {
            padding: 0 4px;
            color: var(--text-muted, #888);
            font-size: 13px;
            line-height: 34px;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1>Dashboard</h1>
                <p class="muted">Interactive insights for employee absences.</p>
            </div>
        </div>
        <nav class="nav-links">
            <a href="admin.php" class="btn btn-outline">Admin View</a>
            <a href="profile.php" class="btn btn-outline">Profile</a>
            <a href="#" class="btn btn-outline" id="logoutBtn">Logout</a>
        </nav>
    </header>

    <main class="container">
        <section class="card" style="margin-bottom: 20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="margin:0; font-size:40px; color:var(--navy);">YEARLY ABSENCES REPORT</h2>
                <form method="GET" style="display:flex; gap:12px; align-items:flex-end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="year">Year</label>
                        <select name="year" id="year">
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $selected_year === $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn" style="padding:10px 20px;">Apply</button>
                </form>
            </div>
        </section>

        <section class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-label">Total Leaves (<?php echo $selected_year; ?>)</div>
                <div class="stat-value"><?php echo (int)($kpi['total_leaves'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Leaves</div>
                <div class="stat-value"><?php echo $active_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Archived Leaves</div>
                <div class="stat-value"><?php echo $archive_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Employees With Leaves</div>
                <div class="stat-value"><?php echo (int)($kpi['employee_count'] ?? 0); ?></div>
            </div>
        </section>

        <section class="chart-grid" style="margin-bottom: 24px;">
            <div class="chart-card">
                <div class="chart-title">Monthly Absences</div>
                <canvas id="monthlyChart" height="280"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Leave Types</div>
                <canvas id="typeChart" height="140"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Active vs Archived</div>
                <canvas id="statusChart" height="140"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Employees on Leave per Month</div>
                <canvas id="empMonthlyChart" height="280"></canvas>
            </div>
        </section>

        <section class="card" style="margin-bottom: 20px;">
            <div class="table-controls">
                <div>
                    <h3 style="margin:0;">Top Employees by Leave Count</h3>
                    <p class="muted" style="margin:4px 0 0;">Top 10 employees for <?php echo $selected_year; ?>.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Leave Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_employees) === 0): ?>
                            <tr><td colspan="2" class="muted">No leave data yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($top_employees as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$row['leave_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="table-controls">
                <div>
                    <h3 style="margin:0;">Recent Absences</h3>
                    <p class="muted" style="margin:4px 0 0;">All records for <?php echo $selected_year; ?>.</p>
                </div>
                <input type="text" id="absenceSearch" class="search-input" placeholder="Search employee or type">
            </div>
            <div class="table-wrap">
                <table id="absenceTable">
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Leave Date</th>
                            <th>Leave Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="absenceTableBody">
                        <?php if (count($recent_records) === 0): ?>
                            <tr><td colspan="4" class="muted">No records yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_records as $row): ?>
                                <tr
                                    data-name="<?php echo strtolower(htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8')); ?>"
                                    data-type="<?php echo strtolower(htmlspecialchars($row['leave_type'], ENT_QUOTES, 'UTF-8')); ?>"
                                >
                                    <td><?php echo htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['leave_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['leave_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $row['is_archived'] ? 'Archived' : 'Active'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo"></span>
                <div class="pagination-controls" id="paginationControls"></div>
            </div>
        </section>
    </main>

    <div id="logoutModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; z-index:2000;">
        <div class="card" style="width:380px; max-width:90%; animation:rise 0.25s ease-out; text-align:center;">
            <div style="margin-bottom:16px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--navy, #1a2e5a)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </div>
            <h3 style="margin:0 0 8px; color:var(--navy, #1a2e5a); font-size:18px;">Log out?</h3>
            <p style="margin:0 0 24px; font-size:14px; color:var(--text-muted, #666);">You will be returned to the login page.</p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button type="button" class="btn btn-auth-outline" id="logoutCancel" style="min-width:110px; border:1px solid var(--border, #ccc);">Cancel</button>
                <a href="logout.php" class="btn" style="min-width:110px; text-decoration:none; text-align:center;">Yes, Log out</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        /* ── Charts ── */
        const monthLabels      = <?php echo json_encode($month_labels); ?>;
        const monthCounts      = <?php echo json_encode($month_counts); ?>;
        const typeLabels       = <?php echo json_encode($type_labels); ?>;
        const typeCounts       = <?php echo json_encode($type_counts); ?>;
        const statusCounts     = <?php echo json_encode([$active_count, $archive_count]); ?>;
        const empMonthlyCounts = <?php echo json_encode($emp_month_counts); ?>;

        const monthlyCtx = document.getElementById('monthlyChart');
        if (monthlyCtx) {
            new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Leaves',
                        data: monthCounts,
                        backgroundColor: '#2563eb',
                        borderRadius: 6,
                        maxBarThickness: 28
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        const typeCtx = document.getElementById('typeChart');
        if (typeCtx) {
            new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        data: typeCounts,
                        backgroundColor: ['#2563eb','#16a34a','#f97316','#7c3aed','#0ea5e9','#dc2626','#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }

        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Archived'],
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: ['#16a34a','#f97316'],
                        borderWidth: 0
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }

        const empMonthlyCtx = document.getElementById('empMonthlyChart');
        if (empMonthlyCtx) {
            new Chart(empMonthlyCtx, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Employees',
                        data: empMonthlyCounts,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124,58,237,0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#7c3aed',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        /* ── Pagination + Search ── */
        (function () {
            const ROWS_PER_PAGE = 10;
            const tbody       = document.getElementById('absenceTableBody');
            const searchInput = document.getElementById('absenceSearch');
            const infoEl      = document.getElementById('paginationInfo');
            const controlsEl  = document.getElementById('paginationControls');

            const allRows     = Array.from(tbody.querySelectorAll('tr[data-name]'));
            let filteredRows  = [...allRows];
            let currentPage   = 1;

            function totalPages() {
                return Math.max(1, Math.ceil(filteredRows.length / ROWS_PER_PAGE));
            }

            function render() {
                const total  = filteredRows.length;
                const pages  = totalPages();
                currentPage  = Math.min(currentPage, pages);
                const start  = (currentPage - 1) * ROWS_PER_PAGE;
                const end    = start + ROWS_PER_PAGE;

                // Show only current page rows
                allRows.forEach(r => r.style.display = 'none');
                filteredRows.forEach((r, i) => {
                    r.style.display = (i >= start && i < end) ? '' : 'none';
                });

                // Info text
                if (total === 0) {
                    infoEl.textContent = 'No records found.';
                } else {
                    infoEl.textContent = `Showing ${start + 1}–${Math.min(end, total)} of ${total} record${total !== 1 ? 's' : ''}`;
                }

                buildControls(pages);
            }

            function buildControls(pages) {
                controlsEl.innerHTML = '';

                function makeBtn(label, page, disabled, active) {
                    const b = document.createElement('button');
                    b.className = 'page-btn' + (active ? ' active' : '');
                    b.textContent = label;
                    b.disabled = disabled;
                    if (!disabled && !active) {
                        b.addEventListener('click', () => { currentPage = page; render(); });
                    }
                    return b;
                }

                function makeEllipsis() {
                    const s = document.createElement('span');
                    s.className = 'page-ellipsis';
                    s.textContent = '…';
                    return s;
                }

                // Prev button
                controlsEl.appendChild(makeBtn('‹ Prev', currentPage - 1, currentPage === 1, false));

                // Page number range with ellipsis
                const range = [];
                if (pages <= 7) {
                    for (let i = 1; i <= pages; i++) range.push(i);
                } else {
                    range.push(1);
                    if (currentPage > 3) range.push('...');
                    for (let i = Math.max(2, currentPage - 1); i <= Math.min(pages - 1, currentPage + 1); i++) {
                        range.push(i);
                    }
                    if (currentPage < pages - 2) range.push('...');
                    range.push(pages);
                }

                range.forEach(item => {
                    controlsEl.appendChild(
                        item === '...'
                            ? makeEllipsis()
                            : makeBtn(item, item, false, item === currentPage)
                    );
                });

                // Next button
                controlsEl.appendChild(makeBtn('Next ›', currentPage + 1, currentPage === pages, false));
            }

            // Search handler — resets to page 1
            searchInput.addEventListener('input', () => {
                const q = searchInput.value.toLowerCase().trim();
                filteredRows = allRows.filter(r =>
                    r.dataset.name.includes(q) || r.dataset.type.includes(q)
                );
                currentPage = 1;
                render();
            });

            // Initial render
            render();
        })();

        /* ── Logout modal ── */
        const logoutModal  = document.getElementById('logoutModal');
        const logoutBtn    = document.getElementById('logoutBtn');
        const logoutCancel = document.getElementById('logoutCancel');

        if (logoutBtn && logoutModal) {
            logoutBtn.addEventListener('click', e => { e.preventDefault(); logoutModal.style.display = 'flex'; });
        }
        if (logoutCancel && logoutModal) {
            logoutCancel.addEventListener('click', () => { logoutModal.style.display = 'none'; });
        }
        if (logoutModal) {
            logoutModal.addEventListener('click', e => { if (e.target === logoutModal) logoutModal.style.display = 'none'; });
        }
    </script>
</body>
</html>