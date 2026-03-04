<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = ''; 

$page = $_GET['page'] ?? 'dashboard';

if (!$user_id && !in_array($page, ['login', 'signup'])) {
    header("Location: ?page=login");
    exit;
}

$logs = [];
$metrics = [
    'rendered_hours' => 0,
    'remaining_hours' => 486,
    'remaining_days' => 0,
    'estimated_date' => '--',
    'is_complete' => false,
    'avg_daily' => 0,
    'req_daily' => 0
];
$goal_hours = 486;

if ($user_id) {
    try {
        $u_stmt = $conn->prepare("SELECT email, name FROM users WHERE id = :id");
        $u_stmt->execute([':id' => $user_id]);
        $user_data = $u_stmt->fetch();
        $user_email = $user_data['email'] ?? '';
        $user_name = $user_data['name'] ?? 'Guest';

        $stmt = $conn->prepare("SELECT * FROM entries WHERE user_id = :user_id ORDER BY log_date DESC, created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $logs = formatLogs($stmt->fetchAll(PDO::FETCH_ASSOC));
        $metrics = getCompletionMetrics($conn, $user_id, $goal_hours);
    } catch (PDOException $e) {}
}

$progress_percent = min(100, ($metrics['rendered_hours'] / $goal_hours) * 100);

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
        <div id="status-message" class="hidden mb-6"></div>

        <?php if ($page === 'dashboard'): ?>
            <header class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-2xl font-bold">Workspace</h1>
                    <p class="text-gray-400 text-sm font-mono lowercase">evaluation & logging</p>
                </div>
                <div class="flex gap-4">
                    <div class="glass-card flex flex-col justify-center px-4 py-2 border-white/5">
                        <span class="text-[8px] text-gray-500 uppercase font-black tracking-widest">Weekly Total</span>
                        <span id="weekly-total-stat" class="text-sm font-mono font-bold text-blue-400">--</span>
                    </div>
                    <div class="glass-card flex flex-col justify-center px-4 py-2 border-white/5">
                        <span class="text-[8px] text-gray-500 uppercase font-black tracking-widest">Status</span>
                        <span id="trend-stat" class="text-[10px] font-black uppercase tracking-widest text-gray-500">...</span>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-8">
                <div class="xl:col-span-1 space-y-6">
                    <button id="toggleFormBtn" class="w-full py-4 glass-card border-dashed border-white/20 text-xs font-black uppercase tracking-widest hover:border-blue-500/50 transition">
                        [ + ] Add New Entry
                    </button>
                    
                    <div id="formContainer" class="glass-card hidden">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Session Data</h2>
                            <div class="flex gap-2">
                                <button type="button" id="markAbsentBtn" class="text-[8px] font-black text-red-500 uppercase tracking-widest border border-red-500/30 px-2 py-1 rounded">Absent</button>
                                <button type="button" id="markPresentBtn" class="hidden text-[8px] font-black text-green-500 uppercase tracking-widest border border-green-500/30 px-2 py-1 rounded">Present</button>
                            </div>
                        </div>
                        <form id="logForm" class="space-y-4">
                            <input type="hidden" name="id" id="entryIdInput">
                            <input type="hidden" name="status" id="statusInput" value="Present">
                            <input type="date" name="log_date" required class="w-full p-3 text-xs">
                            <div id="timeInputs" class="grid grid-cols-2 gap-2">
                                <input type="time" name="start_time" class="w-full p-3 text-xs">
                                <input type="time" name="end_time" class="w-full p-3 text-xs">
                            </div>
                            <div id="tasksInput">
                                <textarea name="tasks" rows="3" placeholder="Description (Optional)" class="w-full p-3 text-xs resize-none"></textarea>
                            </div>
                            <div id="remarksInput" class="hidden">
                                <textarea name="remarks" rows="3" placeholder="Reason..." class="w-full p-3 text-xs resize-none"></textarea>
                            </div>
                            <button type="submit" class="btn-primary w-full uppercase tracking-widest text-[10px] py-3">Deploy Entry</button>
                        </form>
                    </div>

                    <div class="glass-card">
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-4">Weekly Trend</p>
                        <div id="weekly-chart" class="h-24 flex gap-1 items-end">
                            <!-- Bars generated by script.js -->
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-3 space-y-6">
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-4 border-b border-[#24272e] flex flex-wrap gap-4 justify-between items-center bg-white/[0.02]">
                            <div class="flex gap-6 items-center flex-1">
                                <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Sequence Log</h2>
                                <div class="relative flex-1 max-w-xs group">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-3 w-3 text-gray-500 group-focus-within:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </span>
                                    <input type="text" id="tableSearch" placeholder="Filter sequences..." class="w-full bg-[#1b1e26] border border-[#24272e] py-2 pl-9 pr-4 rounded-lg text-xs outline-none font-mono text-gray-300 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 transition-all">
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-[10px] font-black uppercase text-gray-500 tracking-widest">Period:</label>
                                <select id="monthFilter" class="bg-[#1b1e26] border border-[#24272e] px-3 py-2 rounded-lg text-[10px] font-black uppercase text-gray-400 outline-none focus:border-blue-500/50 transition-colors cursor-pointer">
                                    <option value="">All History</option>
                                    <?php 
                                        $months = [];
                                        foreach($logs as $l) $months[] = substr($l['log_date'], 0, 7);
                                        foreach(array_unique($months) as $m) echo "<option value='$m'>".date("M Y", strtotime($m))."</option>";
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="table-header">
                                    <tr>
                                        <th class="px-6 py-4">Session Date</th>
                                        <th class="px-6 py-4">Description</th>
                                        <th class="px-6 py-4 text-right">Hours</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#24272e]">
                                    <!-- Rows generated by script.js -->
                                </tbody>
                            </table>
                        </div>
                        <div class="p-4 flex justify-between items-center border-t border-[#24272e] bg-white/[0.01]">
                            <span id="paginationInfo" class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Page 1 of 1</span>
                            <div class="flex gap-4">
                                <button id="prevPage" class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition">Prev</button>
                                <button id="nextPage" class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'analytics'): ?>
            <header class="mb-8">
                <h1 class="text-2xl font-bold">Analytics</h1>
                <p class="text-gray-400 text-sm font-mono lowercase">Target: <?php echo $goal_hours; ?> Total Hours</p>
            </header>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Average per day</p>
                    <p id="avg-hours-stat" class="text-3xl font-mono font-bold text-blue-400"><?php echo number_format($metrics['avg_daily'], 1); ?>h</p>
                    <p class="text-[8px] text-gray-600 uppercase font-black mt-1">Based on recent sessions</p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Required Daily</p>
                    <p id="req-hours-stat" class="text-3xl font-mono font-bold text-yellow-400"><?php echo number_format($metrics['req_daily'], 1); ?>h</p>
                    <p class="text-[8px] text-gray-600 uppercase font-black mt-1">To finish on schedule</p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Estimated Date</p>
                    <p id="est-date-stat" class="text-xl font-mono font-bold text-purple-400 uppercase"><?php echo $metrics['estimated_date']; ?></p>
                    <p id="est-date-label" class="text-[8px] text-gray-600 uppercase font-black mt-1">Based on average of last 7 days</p>
                </div>
                <div class="glass-card flex flex-col justify-between h-full">
                    <div>
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Status Evaluation</p>
                        <p id="trend-stat" class="text-[10px] font-black uppercase tracking-widest">
                            <?php echo $metrics['avg_daily'] >= $metrics['req_daily'] ? '<span class="text-green-400">Ahead of schedule</span>' : '<span class="text-red-400">Behind schedule</span>'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Remaining Workdays</p>
                    <p id="remaining-days-stat" class="text-3xl font-mono font-bold text-green-400"><?php echo $metrics['remaining_days']; ?></p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Accumulated Hours</p>
                    <p class="text-3xl font-mono font-bold text-blue-400"><?php echo number_format($metrics['rendered_hours'], 2); ?>h</p>
                </div>
            </div>

            <div class="glass-card mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest">Internship Progress</h2>
                    <span class="text-xs font-mono lowercase"><?php echo number_format($metrics['rendered_hours'], 1); ?> / <?php echo $goal_hours; ?> hours</span>
                </div>
                <div class="w-full bg-white/5 h-6 rounded-full overflow-hidden border border-white/10 p-[2px]">
                    <div id="progress-bar" class="bg-blue-600 h-full rounded-full transition-all duration-1000 shadow-[0_0_12px_rgba(37,99,235,0.4)] flex items-center justify-center" style="width: <?php echo $progress_percent; ?>%">
                        <span class="text-[10px] font-black text-white"><?php echo round($progress_percent); ?>%</span>
                    </div>
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
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2 tracking-widest">Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="••••••••" class="w-full p-3 outline-none">
                        </div>
                        <button type="submit" class="btn-primary w-full uppercase tracking-widest text-xs py-3 mt-4">Update Security</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <div id="customModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="glass-card max-w-sm w-full border-white/10 shadow-2xl">
            <h3 id="modalTitle" class="text-lg font-bold mb-2"></h3>
            <p id="modalMessage" class="text-gray-400 text-sm mb-6 font-mono lowercase"></p>
            <div class="flex justify-end gap-3">
                <button id="modalCancel" class="px-4 py-2 text-xs font-black uppercase tracking-widest text-gray-500 hover:text-white transition">Cancel</button>
                <button id="modalConfirm" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-black uppercase tracking-widest transition"></button>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>