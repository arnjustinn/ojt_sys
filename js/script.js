document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const logForm = document.getElementById('logForm');
    const profileForm = document.getElementById('profileForm');
    const passwordForm = document.getElementById('passwordForm');
    const logTableBody = document.querySelector('tbody');
    const statusMessage = document.getElementById('status-message');

    // State Management
    let allLogs = [];
    let currentPage = 1;
    const itemsPerPage = 5;

    // Custom Modal Elements
    const modal = document.getElementById('customModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalCancel = document.getElementById('modalCancel');
    const modalConfirm = document.getElementById('modalConfirm');
    let modalResolver = null;

    function showModal(title, msg, confirmText = "Confirm") {
        modalTitle.textContent = title;
        modalMessage.textContent = msg;
        modalConfirm.textContent = confirmText;
        modal.classList.remove('hidden');
        return new Promise((resolve) => {
            modalResolver = resolve;
        });
    }

    function hideModal(value) {
        modal.classList.add('hidden');
        if (modalResolver) modalResolver(value);
        modalResolver = null;
    }

    if (modalCancel) modalCancel.onclick = () => hideModal(false);
    if (modalConfirm) modalConfirm.onclick = () => hideModal(true);

    // Form Toggle Logic
    const formContainer = document.getElementById('formContainer');
    const toggleFormBtn = document.getElementById('toggleFormBtn');
    if (toggleFormBtn && formContainer) {
        toggleFormBtn.onclick = () => {
            const isHidden = formContainer.classList.contains('hidden');
            formContainer.classList.toggle('hidden');
            toggleFormBtn.textContent = isHidden ? "[ - ] Collapse Form" : "[ + ] Add New Entry";
        };
    }

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
            const tasks = tArea.querySelector('textarea');
            if (tasks) tasks.required = false;
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
            const tasks = tArea.querySelector('textarea');
            if (tasks) tasks.required = true;
        };
    }

    const handleResponse = (res) => {
        if (statusMessage) {
            statusMessage.textContent = res.message.toUpperCase();
            statusMessage.className = `mb-6 p-4 rounded-lg font-bold text-xs uppercase tracking-widest ${res.success ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`;
            statusMessage.classList.remove('hidden');
            setTimeout(() => statusMessage.classList.add('hidden'), 5000);
        }
        
        if (res.success && res.logs) {
            allLogs = res.logs;
            updateTableUI(res.logs, res.grand_total, res.estimated_date, res.remaining_days);
        }
    };

    /**
     * Corrected Start of Week Logic (Resistant to Timezone shifting)
     */
    function getStartOfWeek() {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const day = start.getDay();
        const diff = day === 0 ? -6 : 1 - day; 
        start.setDate(start.getDate() + diff);
        start.setHours(0, 0, 0, 0);
        return start;
    }

    function renderWeeklyChart(logs) {
        const container = document.getElementById('weekly-chart');
        if (!container) return;

        const startOfWeek = getStartOfWeek();
        const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        const dayHours = [0, 0, 0, 0, 0];

        logs.forEach(log => {
            // Split string manually to avoid timezone shifting during Date constructor
            const [y, m, d] = log.log_date.split('-').map(Number);
            const logDate = new Date(y, m - 1, d);
            
            // Calculate absolute day difference
            const diffInDays = Math.round((logDate - startOfWeek) / (1000 * 60 * 60 * 24));
            if (diffInDays >= 0 && diffInDays < 5) {
                dayHours[diffInDays] += parseFloat(log.total_hours);
            }
        });

        const max = Math.max(...dayHours, 8);
        container.innerHTML = dayHours.map((h, i) => `
            <div class="flex-1 flex flex-col items-center justify-end group">
                <div class="w-full bg-blue-600/10 rounded-t relative overflow-hidden" style="height: ${(h / max) * 100}%">
                    <div class="absolute inset-0 bg-blue-500 opacity-40 group-hover:opacity-100 transition-all"></div>
                </div>
                <span class="text-[8px] font-black text-gray-600 uppercase mt-2">${weekDays[i]}</span>
            </div>
        `).join('');
    }

    function updateTableUI(logs, total, estDate, remDays) {
        const hourDisplays = document.querySelectorAll('.total-hours-header');
        const countDisplays = document.querySelectorAll('.total-logs-count');
        const daysLeftDisplays = document.querySelectorAll('.total-days-left, #remaining-days-stat');
        const estDateDisplays = document.querySelectorAll('#est-date-header, #est-date-stat');
        const progressBar = document.getElementById('progress-bar');
        
        const avgDisplay = document.getElementById('avg-hours-stat');
        const reqDisplay = document.getElementById('req-hours-stat');
        const trendDisplay = document.getElementById('trend-stat');
        const weeklyTotalDisplay = document.getElementById('weekly-total-stat');
        
        const goal = 486;
        const progressPercent = Math.min(100, (total / goal) * 100);

        const presentLogs = logs.filter(l => l.status === 'Present');
        const recentLogs = presentLogs.slice(0, 7);
        const avg = recentLogs.length ? (recentLogs.reduce((s, l) => s + parseFloat(l.total_hours), 0) / recentLogs.length) : 0;
        const req = remDays > 0 ? Math.max(0, (goal - total) / remDays) : 0;
        const isAhead = total >= goal || avg >= req;

        // Correct Weekly Total Calculation
        const startOfWeek = getStartOfWeek();
        const weeklyTotal = logs.filter(l => {
            const [y, m, d] = l.log_date.split('-').map(Number);
            return new Date(y, m - 1, d) >= startOfWeek;
        }).reduce((s, l) => s + parseFloat(l.total_hours), 0);

        if (weeklyTotalDisplay) weeklyTotalDisplay.textContent = `${weeklyTotal.toFixed(1)}h`;
        hourDisplays.forEach(el => el.textContent = `${parseFloat(total).toFixed(2)}h`);
        countDisplays.forEach(el => el.textContent = logs.length);
        daysLeftDisplays.forEach(el => {
            const isStat = el.id === 'remaining-days-stat';
            el.textContent = isStat ? remDays : `${remDays}d`;
        });

        if (estDate) estDateDisplays.forEach(el => el.textContent = estDate);
        if (avgDisplay) avgDisplay.textContent = `${avg.toFixed(1)}h`;
        if (reqDisplay) reqDisplay.textContent = `${req.toFixed(1)}h`;
        
        if (trendDisplay) {
            trendDisplay.innerHTML = isAhead ? '<span class="text-green-400">Ahead</span>' : '<span class="text-red-400">Behind</span>';
        }

        if (progressBar) {
            progressBar.style.width = `${progressPercent}%`;
            progressBar.innerHTML = `<span class="flex items-center justify-center h-full text-[10px] font-black text-white">${Math.round(progressPercent)}%</span>`;
        }

        renderWeeklyChart(logs);

        if (!logTableBody) return;
        
        // Filtering and Pagination
        const searchTerm = document.getElementById('tableSearch')?.value.toLowerCase() || "";
        const monthFilter = document.getElementById('monthFilter')?.value || "";
        
        const filtered = logs.filter(l => {
            const matchesSearch = l.tasks.toLowerCase().includes(searchTerm) || l.formatted_date.toLowerCase().includes(searchTerm);
            const matchesMonth = monthFilter === "" || l.log_date.startsWith(monthFilter);
            return matchesSearch && matchesMonth;
        });

        const start = (currentPage - 1) * itemsPerPage;
        const paginated = filtered.slice(start, start + itemsPerPage);

        logTableBody.innerHTML = paginated.length ? paginated.map(log => {
            const isLowHours = log.status === 'Present' && parseFloat(log.total_hours) < 8;
            const taskText = log.tasks || "Daily internship tasks";
            
            return `
                <tr class="table-row ${isLowHours ? 'bg-red-500/5' : ''}">
                    <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">
                        ${log.formatted_date}
                        ${log.status === 'Absent' ? '<span class="ml-2 text-[8px] bg-red-500/20 text-red-400 px-1 rounded uppercase">Absent</span>' : ''}
                    </td>
                    <td class="px-6 py-4 text-xs max-w-xs truncate text-gray-400">
                        ${log.status === 'Absent' ? '<em>Reason: ' + (log.remarks || '') + '</em>' : taskText}
                    </td>
                    <td class="px-6 py-4 text-right font-mono font-bold ${isLowHours ? 'text-red-400' : 'text-blue-400'}">
                        ${parseFloat(log.total_hours).toFixed(2)}
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <button data-id="${log.id}" data-log='${JSON.stringify(log)}' class="edit-btn text-blue-400 hover:text-blue-300 text-[10px] font-black uppercase tracking-widest">[Edit]</button>
                        <button data-id="${log.id}" class="delete-btn text-red-500/60 hover:text-red-500 text-[10px] font-black uppercase tracking-widest">[Remove]</button>
                    </td>
                </tr>
            `;
        }).join('') : '<tr><td colspan="4" class="px-6 py-12 text-center text-gray-500 font-mono text-xs uppercase">No logs detected.</td></tr>';

        // Update Pagination Info
        const totalPages = Math.ceil(filtered.length / itemsPerPage);
        const pageInfo = document.getElementById('paginationInfo');
        if (pageInfo) pageInfo.textContent = `Page ${currentPage} of ${totalPages || 1}`;
    }

    // Pagination Listeners
    document.getElementById('prevPage')?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            updateTableUI(allLogs, 0, null, 0); 
        }
    });

    document.getElementById('nextPage')?.addEventListener('click', () => {
        const filteredCount = allLogs.length; 
        if (currentPage < Math.ceil(filteredCount / itemsPerPage)) {
            currentPage++;
            updateTableUI(allLogs, 0, null, 0);
        }
    });

    // Filter Listeners
    ['tableSearch', 'monthFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            currentPage = 1;
            updateTableUI(allLogs, 0, null, 0);
        });
    });

    async function backgroundRefresh() {
        const idInput = document.getElementById('entryIdInput');
        const editing = idInput && idInput.value !== "";
        const modalOpen = !modal.classList.contains('hidden');

        if (editing || modalOpen) return;

        try {
            const res = await fetch('actions/fetch_log.php');
            const data = await res.json();
            if (data.success && data.logs) {
                allLogs = data.logs;
                updateTableUI(data.logs, data.grand_total, data.estimated_date, data.remaining_days);
            }
        } catch (err) {
            console.error('Console polling failed:', err);
        }
    }

    backgroundRefresh();
    setInterval(backgroundRefresh, 30000);

    if (loginForm) {
        loginForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await fetch('actions/login.php', { method: 'POST', body: new FormData(loginForm) });
            const data = await res.json();
            if (data.success) location.href = '?page=dashboard';
            else handleResponse(data);
        };
    }

    if (signupForm) {
        signupForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await fetch('actions/register.php', { method: 'POST', body: new FormData(signupForm) });
            const data = await res.json();
            if (data.success) location.href = '?page=login';
            else handleResponse(data);
        };
    }

    if (logForm) {
        logForm.onsubmit = async (e) => {
            e.preventDefault();
            const idInput = document.getElementById('entryIdInput');
            const isEdit = idInput && idInput.value !== "";
            const confirmed = await showModal(
                isEdit ? "Update Session" : "Deploy Session", 
                isEdit ? "Commit changes to this sequence?" : "Deploy session to mainframe?",
                isEdit ? "Update" : "Deploy"
            );
            if (!confirmed) return;

            const res = await fetch(isEdit ? 'actions/update_log.php' : 'actions/insert_log.php', {
                method: 'POST',
                body: new FormData(logForm)
            });
            const data = await res.json();
            handleResponse(data);
            if (data.success) {
                logForm.reset();
                if (idInput) idInput.value = "";
                if (toggleFormBtn) toggleFormBtn.click();
                if (mPresentBtn) mPresentBtn.click();
            }
        };
    }

    if (profileForm) {
        profileForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await fetch('actions/update_profile.php', { method: 'POST', body: new FormData(profileForm) });
            handleResponse(await res.json());
        };
    }

    if (passwordForm) {
        passwordForm.onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(passwordForm);
            if (fd.get('new_password') !== fd.get('confirm_password')) {
                handleResponse({ success: false, message: 'Passwords mismatch.' });
                return;
            }
            const res = await fetch('actions/update_password.php', { method: 'POST', body: fd });
            const data = await res.json();
            handleResponse(data);
            if (data.success) passwordForm.reset();
        };
    }

    if (logTableBody) {
        logTableBody.onclick = async (e) => {
            const id = e.target.dataset.id;
            if (e.target.classList.contains('delete-btn')) {
                const confirmed = await showModal("Destroy Record", "Purge this session from console?", "Destroy");
                if (!confirmed) return;
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('actions/delete_log.php', { method: 'POST', body: fd });
                handleResponse(await res.json());
            } else if (e.target.classList.contains('edit-btn')) {
                const log = JSON.parse(e.target.dataset.log);
                const idInput = document.getElementById('entryIdInput');
                if (idInput) idInput.value = log.id;
                logForm.querySelector('[name="log_date"]').value = log.log_date;
                if (log.status === 'Absent') {
                    if (mAbsentBtn) mAbsentBtn.click();
                    logForm.querySelector('[name="remarks"]').value = log.remarks;
                } else {
                    if (mPresentBtn) mPresentBtn.click();
                    logForm.querySelector('[name="start_time"]').value = log.start_time;
                    logForm.querySelector('[name="end_time"]').value = log.end_time;
                    logForm.querySelector('[name="tasks"]').value = log.tasks;
                }
                if (formContainer.classList.contains('hidden')) toggleFormBtn.click();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
    }
});