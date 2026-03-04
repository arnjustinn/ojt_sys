<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = ''; 

$page = $_GET['page'] ?? 'dashboard';

// Redirect logic
if (!$user_id && !in_array($page, ['login', 'signup'])) {
    header("Location: ?page=login");
    exit;
}

$logs = [];
$grand_total = 0;
$total_logs = 0;
$working_days = 0;
$goal_hours = 486;

if ($user_id) {
    try {
        // Fetch user details for settings
        $u_stmt = $conn->prepare("SELECT email, name FROM users WHERE id = :id");
        $u_stmt->execute([':id' => $user_id]);
        $user_data = $u_stmt->fetch();
        $user_email = $user_data['email'] ?? '';
        $user_name = $user_data['name'] ?? 'Guest';

        // Fetch logs
        $stmt = $conn->prepare("SELECT * FROM entries WHERE user_id = :user_id ORDER BY log_date DESC, created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $logs = formatLogs($stmt->fetchAll(PDO::FETCH_ASSOC));
        
        $grand_total = array_sum(array_column($logs, 'total_hours'));
        $total_logs = count($logs);
        
        // Count unique days where student was present
        $present_dates = [];
        foreach($logs as $l) {
            if ($l['status'] === 'Present') {
                $present_dates[] = $l['log_date'];
            }
        }
        $working_days = count(array_unique($present_dates));
        
    } catch (PDOException $e) {}
}

$remaining_hours = max(0, $goal_hours - $grand_total);
$progress_percent = min(100, ($grand_total / $goal_hours) * 100);

function isActive($currentPage, $linkPage) {
    return $currentPage === $linkPage ? 'bg-blue-600/10 text-blue-400' : 'text-gray-400 hover:bg-white/5';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OJT Console - <?php echo ucfirst($page); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-[#090a0f] text-white overflow-x-hidden">

    <?php if ($user_id): ?>
    <aside class="sidebar hidden md:flex flex-col">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-10">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-bold">O</div>
                <span class="text-xl font-bold tracking-tight">OJT Console</span>
            </div>
            <nav class="space-y-1">
                <a href="?page=dashboard" class="flex items-center gap-3 p-3 rounded-lg font-medium transition <?php echo isActive($page, 'dashboard'); ?>">Dashboard</a>
                <a href="?page=analytics" class="flex items-center gap-3 p-3 rounded-lg font-medium transition <?php echo isActive($page, 'analytics'); ?>">Analytics</a>
                <a href="?page=settings" class="flex items-center gap-3 p-3 rounded-lg font-medium transition <?php echo isActive($page, 'settings'); ?>">Settings</a>
            </nav>
        </div>
        <div class="mt-auto px-6 pb-6 space-y-4">
            <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                <div class="w-10 h-10 bg-gray-800 rounded-full border border-white/10 flex items-center justify-center font-bold text-blue-400"><?php echo substr($user_name, 0, 1); ?></div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Intern</p>
                    <p class="text-sm font-bold truncate"><?php echo htmlspecialchars($user_name); ?></p>
                </div>
                <a href="actions/logout.php" class="p-2 text-gray-500 hover:text-red-400 transition">Logout</a>
            </div>
        </div>
    </aside>
    <?php endif; ?>

    <main class="<?php echo $user_id ? 'main-content' : 'flex items-center justify-center min-h-screen'; ?>">
        <!-- System Alerts -->
        <div id="status-message" class="hidden mb-6"></div>

        <?php if ($page === 'login'): ?>
            <div class="glass-card w-full max-w-[400px]">
                <h2 class="text-xl font-bold mb-2">Access Console</h2>
                <p class="text-gray-400 text-sm mb-6 font-mono lowercase">Enter identity parameters</p>
                <form id="loginForm" class="space-y-4">
                    <input type="email" name="email" placeholder="Email" required class="w-full p-3 outline-none transition">
                    <input type="password" name="password" placeholder="Password" required class="w-full p-3 outline-none transition">
                    <button type="submit" class="btn-primary w-full mt-2 uppercase tracking-widest text-xs py-3">Login</button>
                </form>
                <p class="text-center text-xs text-gray-500 mt-6 font-mono uppercase">New sequence? <a href="?page=signup" class="text-blue-400 font-bold hover:underline">Register</a></p>
            </div>

        <?php elseif ($page === 'signup'): ?>
            <div class="glass-card w-full max-w-[400px]">
                <h2 class="text-xl font-bold mb-2">Create Identity</h2>
                <p class="text-gray-400 text-sm mb-6 font-mono lowercase">Register new intern sequence</p>
                <form id="signupForm" class="space-y-4">
                    <input type="text" name="name" placeholder="Full Name" required class="w-full p-3 outline-none transition">
                    <input type="email" name="email" placeholder="Email Address" required class="w-full p-3 outline-none transition">
                    <input type="password" name="password" placeholder="Password" required class="w-full p-3 outline-none transition">
                    <button type="submit" class="btn-primary w-full mt-2 uppercase tracking-widest text-xs py-3">Initialize Account</button>
                </form>
                <p class="text-center text-xs text-gray-500 mt-6 font-mono uppercase">Identity exists? <a href="?page=login" class="text-blue-400 font-bold hover:underline">Login</a></p>
            </div>

        <?php elseif ($page === 'dashboard'): ?>
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold">Activity Dashboard</h1>
                    <p class="text-gray-400 text-sm font-mono lowercase">Session Metrics</p>
                </div>
                <div class="flex gap-4">
                    <div class="glass-card flex items-center gap-4 py-2 px-4">
                        <span class="text-[10px] text-gray-400 uppercase font-black tracking-widest">Logs</span>
                        <span class="total-logs-count text-xl font-mono font-bold text-blue-400"><?php echo $total_logs; ?></span>
                    </div>
                    <div class="glass-card flex items-center gap-4 py-2 px-4">
                        <span class="text-[10px] text-gray-400 uppercase font-black tracking-widest">Hours</span>
                        <span class="total-hours-header text-xl font-mono font-bold text-blue-400"><?php echo number_format($grand_total, 2); ?>h</span>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <!-- Form -->
                <section class="xl:col-span-1">
                    <div class="glass-card">
                        <div class="flex justify-between items-center mb-6">
                            <h2 id="formTitle" class="text-xs font-black text-gray-500 uppercase tracking-widest">New Entry</h2>
                            <div class="flex gap-2">
                                <button type="button" id="markAbsentBtn" class="text-[10px] font-black text-red-500 uppercase tracking-widest border border-red-500/30 px-2 py-1 rounded hover:bg-red-500/10 transition">Mark Absent</button>
                                <button type="button" id="markPresentBtn" class="hidden text-[10px] font-black text-green-500 uppercase tracking-widest border border-green-500/30 px-2 py-1 rounded hover:bg-green-500/10 transition">Mark Present</button>
                            </div>
                        </div>
                        
                        <form id="logForm" class="space-y-4">
                            <input type="hidden" name="id" id="entryIdInput">
                            <input type="hidden" name="status" id="statusInput" value="Present">
                            
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">Date</label>
                                <input type="date" name="log_date" required class="w-full p-3 outline-none">
                            </div>
                            
                            <div id="timeInputs" class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">In</label>
                                    <input type="time" name="start_time" class="w-full p-3 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">Out</label>
                                    <input type="time" name="end_time" class="w-full p-3 outline-none">
                                </div>
                            </div>

                            <div id="tasksInput">
                                <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">Tasks</label>
                                <textarea name="tasks" rows="5" placeholder="Session details..." class="w-full p-3 outline-none resize-none" required></textarea>
                            </div>

                            <div id="remarksInput" class="hidden">
                                <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">Absence Reason</label>
                                <textarea name="remarks" rows="5" placeholder="Reason for absence..." class="w-full p-3 outline-none resize-none"></textarea>
                            </div>

                            <button type="submit" id="submitBtn" class="btn-primary w-full mt-2 uppercase tracking-widest text-xs py-3">Deploy Entry</button>
                        </form>
                    </div>
                </section>

                <!-- History -->
                <section class="xl:col-span-2">
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-4 border-b border-[#24272e]">
                            <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest">Sequence Log History</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="table-header">
                                    <tr>
                                        <th class="px-6 py-4">Date</th>
                                        <th class="px-6 py-4">Interval</th>
                                        <th class="px-6 py-4">Description</th>
                                        <th class="px-6 py-4 text-right">Hours</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#24272e]">
                                    <?php if(empty($logs)): ?>
                                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 font-mono text-xs uppercase">No logs detected.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr class="table-row">
                                                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">
                                                    <?php echo $log['formatted_date']; ?>
                                                    <?php if($log['status'] === 'Absent'): ?>
                                                        <span class="ml-2 text-[8px] bg-red-500/20 text-red-400 px-1 rounded">ABSENT</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                                                    <?php echo $log['status'] === 'Absent' ? '-- : --' : $log['formatted_start'] . ' - ' . $log['formatted_end']; ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm max-w-xs truncate text-gray-400">
                                                    <?php echo $log['status'] === 'Absent' ? '<em>Reason: ' . htmlspecialchars($log['remarks'] ?? '') . '</em>' : $log['tasks']; ?>
                                                </td>
                                                <td class="px-6 py-4 text-right font-mono font-bold text-blue-400">
                                                    <?php echo number_format($log['total_hours'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 text-right space-x-2">
                                                    <button data-id="<?php echo $log['id']; ?>" data-log='<?php echo json_encode($log); ?>' class="edit-btn text-blue-400 hover:text-blue-300 text-[10px] font-black uppercase tracking-widest">[Edit]</button>
                                                    <button data-id="<?php echo $log['id']; ?>" class="delete-btn text-red-500/60 hover:text-red-500 text-[10px] font-black uppercase tracking-widest">[Remove]</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

        <?php elseif ($page === 'analytics'): ?>
            <header class="mb-8">
                <h1 class="text-2xl font-bold">Analytics</h1>
                <p class="text-gray-400 text-sm font-mono lowercase">Target: <?php echo $goal_hours; ?> Total Hours</p>
            </header>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Working Days</p>
                    <p class="text-3xl font-mono font-bold text-green-400"><?php echo $working_days; ?></p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Total Accumulated</p>
                    <p class="text-3xl font-mono font-bold text-blue-400"><?php echo number_format($grand_total, 2); ?>h</p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Remaining Goal</p>
                    <p class="text-3xl font-mono font-bold text-purple-400"><?php echo number_format($remaining_hours, 2); ?>h</p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Completion</p>
                    <p class="text-3xl font-mono font-bold text-yellow-400"><?php echo number_format($progress_percent, 1); ?>%</p>
                </div>
            </div>

            <div class="glass-card mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest">Internship Progress</h2>
                    <span class="text-xs font-mono lowercase"><?php echo number_format($grand_total, 1); ?> / <?php echo $goal_hours; ?> hours</span>
                </div>
                <div class="w-full bg-white/5 h-4 rounded-full overflow-hidden border border-white/10 p-[2px]">
                    <div class="bg-blue-600 h-full rounded-full transition-all duration-1000 shadow-[0_0_12px_rgba(37,99,235,0.4)]" style="width: <?php echo $progress_percent; ?>%"></div>
                </div>
            </div>

        <?php elseif ($page === 'settings'): ?>
            <header class="mb-8">
                <h1 class="text-2xl font-bold">Account Settings</h1>
                <p class="text-gray-400 text-sm font-mono lowercase">Manage profile identity and parameters</p>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="glass-card">
                    <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-6">Profile Information</h2>
                    <form id="profileForm" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user_name); ?>" class="w-full p-3 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">Email Identity</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" class="w-full p-3 outline-none">
                        </div>
                        <button type="submit" class="btn-primary w-full uppercase tracking-widest text-xs py-3 mt-4">Save Changes</button>
                    </form>
                </div>

                <div class="glass-card">
                    <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-6">Security Update</h2>
                    <form id="passwordForm" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">New Password</label>
                            <input type="password" name="new_password" placeholder="••••••••" class="w-full p-3 outline-none">
                        </div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Encrypted via console standards</p>
                        <button type="submit" class="btn-primary w-full uppercase tracking-widest text-xs py-3 mt-4">Update Security</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Form Listeners
        document.addEventListener('DOMContentLoaded', () => {
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');
            const logForm = document.getElementById('logForm');
            const logTableBody = document.querySelector('tbody');
            const statusMessage = document.getElementById('status-message');

            // Absence Toggle
            const mAbsentBtn = document.getElementById('markAbsentBtn');
            const mPresentBtn = document.getElementById('markPresentBtn');
            const tInputs = document.getElementById('timeInputs');
            const tArea = document.getElementById('tasksInput');
            const rArea = document.getElementById('remarksInput');
            const sInput = document.getElementById('statusInput');

            if (mAbsentBtn) {
                mAbsentBtn.onclick = () => {
                    sInput.value = 'Absent';
                    tInputs.classList.add('hidden');
                    tArea.classList.add('hidden');
                    rArea.classList.remove('hidden');
                    mAbsentBtn.classList.add('hidden');
                    mPresentBtn.classList.remove('hidden');
                    tArea.querySelector('textarea').required = false;
                };
            }
            if (mPresentBtn) {
                mPresentBtn.onclick = () => {
                    sInput.value = 'Present';
                    tInputs.classList.remove('hidden');
                    tArea.classList.remove('hidden');
                    rArea.classList.add('hidden');
                    mPresentBtn.classList.add('hidden');
                    mAbsentBtn.classList.remove('hidden');
                    tArea.querySelector('textarea').required = true;
                };
            }

            // Shared Response Handler
            const handleResponse = (res) => {
                if (statusMessage) {
                    statusMessage.textContent = res.message.toUpperCase();
                    statusMessage.className = `mb-6 p-4 rounded-lg font-bold text-xs uppercase tracking-widest ${res.success ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`;
                    statusMessage.classList.remove('hidden');
                    setTimeout(() => statusMessage.classList.add('hidden'), 5000);
                }
                if (res.success && res.logs) updateTableUI(res.logs, res.grand_total);
            };

            // Global table update
            function updateTableUI(logs, total) {
                if (document.querySelector('.total-hours-header')) {
                    document.querySelector('.total-hours-header').textContent = `${parseFloat(total).toFixed(2)}h`;
                }
                if (document.querySelector('.total-logs-count')) {
                    document.querySelector('.total-logs-count').textContent = logs.length;
                }
                if (!logTableBody) return;
                
                logTableBody.innerHTML = logs.length ? logs.map(log => `
                    <tr class="table-row">
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">
                            ${log.formatted_date}
                            ${log.status === 'Absent' ? '<span class="ml-2 text-[8px] bg-red-500/20 text-red-400 px-1 rounded">ABSENT</span>' : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                            ${log.status === 'Absent' ? '-- : --' : log.formatted_start + ' - ' + log.formatted_end}
                        </td>
                        <td class="px-6 py-4 text-sm max-w-xs truncate text-gray-400">
                            ${log.status === 'Absent' ? '<em>Reason: ' + (log.remarks || '') + '</em>' : log.tasks}
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-blue-400">
                            ${parseFloat(log.total_hours).toFixed(2)}
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button data-id="${log.id}" data-log='${JSON.stringify(log)}' class="edit-btn text-blue-400 hover:text-blue-300 text-[10px] font-black uppercase tracking-widest">[Edit]</button>
                            <button data-id="${log.id}" class="delete-btn text-red-500/60 hover:text-red-500 text-[10px] font-black uppercase tracking-widest">[Remove]</button>
                        </td>
                    </tr>
                `).join('') : '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 font-mono text-xs uppercase">No logs detected.</td></tr>';
            }

            // Auth Handlers
            if (loginForm) {
                loginForm.onsubmit = async (e) => {
                    e.preventDefault();
                    const r = await fetch('actions/login.php', { method: 'POST', body: new FormData(loginForm) });
                    const d = await r.json();
                    if (d.success) location.href = '?page=dashboard';
                    else handleResponse(d);
                };
            }
            if (signupForm) {
                signupForm.onsubmit = async (e) => {
                    e.preventDefault();
                    const r = await fetch('actions/register.php', { method: 'POST', body: new FormData(signupForm) });
                    const d = await r.json();
                    if (d.success) location.href = '?page=login';
                    else handleResponse(d);
                };
            }

            // Log Form Handler
            if (logForm) {
                logForm.onsubmit = async (e) => {
                    e.preventDefault();
                    const isEdit = document.getElementById('entryIdInput').value !== "";
                    const r = await fetch(isEdit ? 'actions/update_log.php' : 'actions/insert_log.php', {
                        method: 'POST',
                        body: new FormData(logForm)
                    });
                    const d = await r.json();
                    handleResponse(d);
                    if (d.success) {
                        logForm.reset();
                        document.getElementById('entryIdInput').value = "";
                        document.getElementById('formTitle').textContent = "New Entry";
                        document.getElementById('submitBtn').textContent = "Deploy Entry";
                        if (mPresentBtn) mPresentBtn.click();
                    }
                };
            }

            // Table Action Delegation
            if (logTableBody) {
                logTableBody.onclick = async (e) => {
                    const id = e.target.dataset.id;
                    if (e.target.classList.contains('delete-btn')) {
                        if (!confirm('Destroy record?')) return;
                        const fd = new FormData();
                        fd.append('id', id);
                        const r = await fetch('actions/delete_log.php', { method: 'POST', body: fd });
                        handleResponse(await r.json());
                    } else if (e.target.classList.contains('edit-btn')) {
                        const log = JSON.parse(e.target.dataset.log);
                        document.getElementById('formTitle').textContent = "Edit Entry";
                        document.getElementById('submitBtn').textContent = "Update Entry";
                        document.getElementById('entryIdInput').value = log.id;
                        logForm.querySelector('[name="log_date"]').value = log.log_date;
                        if (log.status === 'Absent') {
                            mAbsentBtn.click();
                            logForm.querySelector('[name="remarks"]').value = log.remarks;
                        } else {
                            mPresentBtn.click();
                            logForm.querySelector('[name="start_time"]').value = log.start_time;
                            logForm.querySelector('[name="end_time"]').value = log.end_time;
                            logForm.querySelector('[name="tasks"]').value = log.tasks;
                        }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                };
            }
        });
    </script>
</body>
</html>