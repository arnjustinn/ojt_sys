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
    'is_complete' => false
];
$working_days = 0;
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
        
        // Fetch dynamic completion metrics from functions.php
        $metrics = getCompletionMetrics($conn, $user_id, $goal_hours);
        
        $present_dates = [];
        foreach($logs as $l) {
            if ($l['status'] === 'Present') {
                $present_dates[] = $l['log_date'];
            }
        }
        $working_days = count(array_unique($present_dates));
        
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

        <?php if ($page === 'login'): ?>
            <!-- Login Form -->
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
            <!-- Signup Form -->
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
                    <div class="glass-card flex flex-col justify-center px-4 py-2 border-green-500/20">
                        <span class="text-[8px] text-gray-500 uppercase font-black tracking-widest">Est. Completion</span>
                        <span id="est-date-header" class="text-sm font-mono font-bold text-green-400"><?php echo $metrics['estimated_date']; ?></span>
                    </div>
                    <div class="glass-card flex flex-col justify-center px-4 py-2">
                        <span class="text-[8px] text-gray-500 uppercase font-black tracking-widest">Days Left</span>
                        <span class="total-days-left text-sm font-mono font-bold text-blue-400"><?php echo $metrics['remaining_days']; ?>d</span>
                    </div>
                    <div class="glass-card flex flex-col justify-center px-4 py-2">
                        <span class="text-[8px] text-gray-500 uppercase font-black tracking-widest">Total Hours</span>
                        <span class="total-hours-header text-xl font-mono font-bold text-blue-400"><?php echo number_format($metrics['rendered_hours'], 2); ?>h</span>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
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

                <section class="xl:col-span-2">
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-4 border-b border-[#24272e] flex justify-between items-center">
                            <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest">Sequence Log History</h2>
                            <div class="text-[10px] font-mono text-gray-500">
                                Total Logs: <span class="total-logs-count"><?php echo count($logs); ?></span>
                            </div>
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
                                                        <span class="ml-2 text-[8px] bg-red-500/20 text-red-400 px-1 rounded uppercase">Absent</span>
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
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Remaining Workdays</p>
                    <p id="remaining-days-stat" class="text-3xl font-mono font-bold text-green-400"><?php echo $metrics['remaining_days']; ?></p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Total Accumulated</p>
                    <p class="text-3xl font-mono font-bold text-blue-400"><?php echo number_format($metrics['rendered_hours'], 2); ?>h</p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Estimated Date</p>
                    <p id="est-date-stat" class="text-xl font-mono font-bold text-purple-400 uppercase"><?php echo $metrics['estimated_date']; ?></p>
                </div>
                <div class="glass-card">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">Completion</p>
                    <p class="text-3xl font-mono font-bold text-yellow-400"><?php echo number_format($progress_percent, 1); ?>%</p>
                </div>
            </div>

            <div class="glass-card mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest">Internship Progress</h2>
                    <span class="text-xs font-mono lowercase"><?php echo number_format($metrics['rendered_hours'], 1); ?> / <?php echo $goal_hours; ?> hours</span>
                </div>
                <div class="w-full bg-white/5 h-4 rounded-full overflow-hidden border border-white/10 p-[2px]">
                    <div id="progress-bar" class="bg-blue-600 h-full rounded-full transition-all duration-1000 shadow-[0_0_12px_rgba(37,99,235,0.4)]" style="width: <?php echo $progress_percent; ?>%"></div>
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

    <!-- Custom Console Modal -->
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

    <!-- Scripts -->
    <script src="js/script.js"></script>
</body>
</html>