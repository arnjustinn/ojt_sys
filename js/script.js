document.addEventListener('DOMContentLoaded', () => {
    const logForm = document.getElementById('logForm');
    const logTableBody = document.querySelector('tbody');
    const statusMessage = document.getElementById('status-message');
    const absentBtn = document.getElementById('markAbsentBtn');
    const presentBtn = document.getElementById('markPresentBtn');
    const timeInputs = document.getElementById('timeInputs');
    const tasksInput = document.getElementById('tasksInput');
    const remarksInput = document.getElementById('remarksInput');
    const statusInput = document.getElementById('statusInput');
    const formTitle = document.getElementById('formTitle');
    const entryIdInput = document.getElementById('entryIdInput');
    const submitBtn = document.getElementById('submitBtn');

    // Toggle Absent Mode
    if (absentBtn) {
        absentBtn.addEventListener('click', () => {
            statusInput.value = 'Absent';
            timeInputs.classList.add('hidden');
            tasksInput.classList.add('hidden');
            remarksInput.classList.remove('hidden');
            absentBtn.classList.add('hidden');
            presentBtn.classList.remove('hidden');
            document.querySelector('[name="tasks"]').required = false;
        });
    }

    if (presentBtn) {
        presentBtn.addEventListener('click', () => {
            statusInput.value = 'Present';
            timeInputs.classList.remove('hidden');
            tasksInput.classList.remove('hidden');
            remarksInput.classList.add('hidden');
            presentBtn.classList.add('hidden');
            absentBtn.classList.remove('hidden');
            document.querySelector('[name="tasks"]').required = true;
        });
    }

    // Handle Submission
    if (logForm) {
        logForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = entryIdInput.value !== "";
            const endpoint = isEdit ? 'actions/update_log.php' : 'actions/insert_log.php';
            const formData = new FormData(logForm);
            
            try {
                const response = await fetch(endpoint, { method: 'POST', body: formData });
                const result = await response.json();
                handleResponse(result);
                if (result.success) resetForm();
            } catch (err) {
                showFeedback('Submission failed', false);
            }
        });
    }

    // Handle Actions (Edit/Delete)
    if (logTableBody) {
        logTableBody.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            if (e.target.classList.contains('delete-btn')) {
                if (!confirm('Remove this entry?')) return;
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('actions/delete_log.php', { method: 'POST', body: fd });
                handleResponse(await res.json());
            } else if (e.target.classList.contains('edit-btn')) {
                prepareEdit(JSON.parse(e.target.dataset.log));
            }
        });
    }

    function prepareEdit(log) {
        formTitle.textContent = "Edit Entry";
        submitBtn.textContent = "Update Entry";
        entryIdInput.value = log.id;
        logForm.querySelector('[name="log_date"]').value = log.log_date;
        
        if (log.status === 'Absent') {
            absentBtn.click();
            logForm.querySelector('[name="remarks"]').value = log.remarks;
        } else {
            presentBtn.click();
            logForm.querySelector('[name="start_time"]').value = log.start_time;
            logForm.querySelector('[name="end_time"]').value = log.end_time;
            logForm.querySelector('[name="tasks"]').value = log.tasks;
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        logForm.reset();
        entryIdInput.value = "";
        formTitle.textContent = "New Entry";
        submitBtn.textContent = "Deploy Entry";
        presentBtn.click();
    }

    function handleResponse(res) {
        showFeedback(res.message, res.success);
        if (res.success) updateUI(res.logs, res.grand_total);
    }

    function showFeedback(msg, ok) {
        statusMessage.textContent = msg;
        statusMessage.className = `mb-6 p-4 rounded-lg font-bold text-xs uppercase tracking-widest ${ok ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'}`;
        statusMessage.classList.remove('hidden');
        setTimeout(() => statusMessage.classList.add('hidden'), 5000);
    }

    function updateUI(logs, total) {
        document.querySelector('.total-hours-header').textContent = `${parseFloat(total).toFixed(2)}h`;
        document.querySelector('.total-logs-count').textContent = logs.length;
        
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
                    ${log.status === 'Absent' ? '<em>Reason: ' + log.remarks + '</em>' : log.tasks}
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
});