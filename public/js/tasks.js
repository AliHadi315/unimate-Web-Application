

let allTasks   = [];
let allCourses = [];
let currentView = 'list';
let calYear, calMonth; // currently displayed calendar month

const PRIORITY_RANK  = { High: 0, Medium: 1, Low: 2 };
const PRIORITY_BADGE = { High: 'badge-red', Medium: 'badge-amber', Low: 'badge-green' };

/*  INIT  */

document.addEventListener('DOMContentLoaded', async () => {
    if (!requireAuth()) return;
    renderSidebar('tasks.html');
    document.getElementById('s-file').addEventListener('change', loadSyllabusFile);
    await loadData();
});

async function loadData() {
    document.getElementById('tasks-list').innerHTML = skeletonRows(5);

    try {
        const [tasks, courses] = await Promise.all([
            TasksAPI.list(),
            CoursesAPI.list(),
        ]);
        allTasks   = tasks;
        allCourses = courses;
        populateCourseDropdowns();
        renderTasks();
    } catch (err) {
        showToast('Failed to load tasks.', 'error');
    }
}

/*  COURSE DROPDOWNS  */

function populateCourseDropdowns() {
    const filterSel = document.getElementById('filter-course');
    const taskSel   = document.getElementById('t-course');

    filterSel.innerHTML = '<option value="all">All Courses</option>' +
        allCourses.map(c => `<option value="${c.id}">${escapeHtml(c.code)} — ${escapeHtml(c.name)}</option>`).join('');

    taskSel.innerHTML = '<option value="">— Select a course —</option>' +
        allCourses.map(c => `<option value="${c.id}">${escapeHtml(c.code)} — ${escapeHtml(c.name)}</option>`).join('');
}

/*  RESET FILTERS  */

function resetFilters() {
    document.getElementById('search-input').value    = '';
    document.getElementById('filter-status').value   = 'all';
    document.getElementById('filter-type').value     = 'all';
    document.getElementById('filter-priority').value = 'all';
    document.getElementById('filter-course').value   = 'all';
    document.getElementById('sort-field').value      = 'due_date';
    renderTasks();
}

/*  RENDER TASKS  */

function getFilteredTasks() {
    const q        = document.getElementById('search-input').value.trim().toLowerCase();
    const status   = document.getElementById('filter-status').value;
    const type     = document.getElementById('filter-type').value;
    const priority = document.getElementById('filter-priority').value;
    const courseId = document.getElementById('filter-course').value;

    return allTasks.filter(t => {
        const days = daysUntil(t.due_date);
        if (q && !t.title.toLowerCase().includes(q))                return false;
        if (type !== 'all'     && t.type !== type)                   return false;
        if (priority !== 'all' && t.priority !== priority)           return false;
        if (courseId !== 'all' && t.course_id !== parseInt(courseId)) return false;
        if (status === 'pending')   return !t.is_completed;
        if (status === 'completed') return t.is_completed;
        if (status === 'overdue')   return isOverdue(t);
        if (status === 'today')     return days === 0;
        if (status === 'thisWeek')  return days >= 0 && days <= 7;
        return true;
    });
}

function renderTasks() {
    if (currentView === 'calendar') { renderCalendar(); return; }
    renderList();
}

function renderList() {
    const sortBy = document.getElementById('sort-field').value;

    const courseMap = {};
    allCourses.forEach(c => { courseMap[c.id] = c; });

    let tasks = getFilteredTasks();

    tasks.sort((a, b) => {
        if (sortBy === 'due_date') return parseDate(a.due_date) - parseDate(b.due_date);
        if (sortBy === 'priority') return PRIORITY_RANK[a.priority] - PRIORITY_RANK[b.priority];
        if (sortBy === 'title')    return a.title.localeCompare(b.title);
        return 0;
    });

    const el = document.getElementById('tasks-list');

    if (tasks.length === 0) {
        el.innerHTML = `
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <h4>No tasks found</h4>
                <p>Adjust your filters or add a task using the button above.</p>
            </div>`;
        return;
    }

    el.innerHTML = tasks.map((t, i) => {
        const overdue = isOverdue(t);
        const days    = daysUntil(t.due_date);
        const pb      = PRIORITY_BADGE[t.priority] || 'badge-gray';
        const course  = courseMap[t.course_id];
        const isLast  = i === tasks.length - 1;

        let dueTxt;
        if (overdue)      dueTxt = `<span class="text-danger font-bold">${Math.abs(days)}d overdue</span>`;
        else if (days===0) dueTxt = `<span style="color:var(--amber);font-weight:600">Due today</span>`;
        else              dueTxt = `Due ${formatDate(t.due_date)}`;

        return `
            <div class="task-list-row" style="${isLast ? '' : 'border-bottom:1px solid var(--border);'}">
                <input type="checkbox" ${t.is_completed ? 'checked' : ''}
                    onchange="toggleTask(${t.id}, this.checked)"
                    class="task-checkbox"/>
                <div class="task-info">
                    <div class="task-title ${t.is_completed ? 'strike' : ''}">${escapeHtml(t.title)}</div>
                    <div class="task-meta">
                        <span class="text-sm text-muted">${t.type}</span>
                        <span class="text-sm text-muted">·</span>
                        <span class="text-sm">${dueTxt}</span>
                        ${course ? `<span class="badge badge-gray" style="font-size:.68rem">${escapeHtml(course.code)}</span>` : ''}
                        ${attachmentLink(t)}
                    </div>
                </div>
                <span class="badge ${pb}">${t.priority}</span>
                <div class="task-actions">
                    <button class="btn btn-ghost btn-sm" onclick="openEditTask(${t.id})">Edit</button>
                    <button class="btn btn-ghost btn-sm text-danger" onclick="promptDeleteTask(${t.id})">Delete</button>
                </div>
            </div>`;
    }).join('');
}

/*  CALENDAR VIEW  */

function setView(view) {
    currentView = view;
    document.getElementById('view-list-btn').classList.toggle('active', view === 'list');
    document.getElementById('view-cal-btn').classList.toggle('active', view === 'calendar');
    document.getElementById('list-view').classList.toggle('hidden', view !== 'list');
    document.getElementById('calendar-view').classList.toggle('hidden', view !== 'calendar');
    renderTasks();
}

function calShift(delta) {
    calMonth += delta;
    if (calMonth < 0)  { calMonth = 11; calYear--; }
    if (calMonth > 11) { calMonth = 0;  calYear++; }
    renderCalendar();
}

function calToday() {
    calYear  = undefined;
    calMonth = undefined;
    renderCalendar();
}

function renderCalendar() {
    const today = startOfToday();
    if (calYear === undefined || calMonth === undefined) {
        calYear  = today.getFullYear();
        calMonth = today.getMonth();
    }

    document.getElementById('cal-title').textContent =
        new Date(calYear, calMonth, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    // Group filtered tasks by due date
    const byDate = {};
    getFilteredTasks().forEach(t => {
        (byDate[t.due_date] = byDate[t.due_date] || []).push(t);
    });

    const firstOfMonth = new Date(calYear, calMonth, 1);
    const lead         = (firstOfMonth.getDay() + 6) % 7; // week starts Monday
    const gridStart    = new Date(calYear, calMonth, 1 - lead);

    let html = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
        .map(d => `<div class="cal-dow">${d}</div>`).join('');

    for (let i = 0; i < 42; i++) {
        const day     = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
        const iso     = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
        const inMonth = day.getMonth() === calMonth;
        const isToday = day.getTime() === today.getTime();
        const tasks   = byDate[iso] || [];

        const chips = tasks.slice(0, 3).map(t => `
            <div class="cal-chip p-${t.priority.toLowerCase()}${t.is_completed ? ' done' : ''}"
                onclick="openEditTask(${t.id})" title="${escapeHtml(t.title)} — ${t.type}">
                ${escapeHtml(t.title)}
            </div>`).join('');

        const more = tasks.length > 3
            ? `<div class="cal-more">+${tasks.length - 3} more</div>` : '';

        html += `
            <div class="cal-cell${inMonth ? '' : ' other'}${isToday ? ' today' : ''}">
                <div class="cal-day-num">${day.getDate()}</div>
                ${chips}${more}
            </div>`;
    }

    document.getElementById('cal-grid').innerHTML = html;
}

/*  TASK CRUD  */

async function toggleTask(id, checked) {
    try {
        const updated = await TasksAPI.toggle(id);
        allTasks = allTasks.map(t => t.id === id ? updated : t);
        renderTasks();
    } catch (err) {
        showToast('Failed to update task.', 'error');
    }
}

function openAddTask() {
    document.getElementById('task-modal-title').textContent = 'Add Task';
    document.getElementById('t-edit-id').value              = '';
    document.getElementById('t-title').value                = '';
    document.getElementById('t-course').value               = '';
    document.getElementById('t-type').value                 = 'Assignment';
    document.getElementById('t-priority').value             = 'Medium';
    document.getElementById('t-due').value                  = new Date().toISOString().split('T')[0];
    document.getElementById('t-completed').checked          = false;
    resetTaskFileField();
    document.querySelectorAll('#modal-task .form-error').forEach(e => e.classList.remove('show'));
    document.querySelectorAll('#modal-task .form-control').forEach(e => e.style.borderColor = '');
    openModal('modal-task');
}

function openEditTask(id) {
    const t = allTasks.find(x => x.id === id);
    if (!t) return;
    document.getElementById('task-modal-title').textContent = 'Edit Task';
    document.getElementById('t-edit-id').value              = t.id;
    document.getElementById('t-title').value                = t.title;
    document.getElementById('t-course').value               = t.course_id;
    document.getElementById('t-type').value                 = t.type;
    document.getElementById('t-priority').value             = t.priority;
    document.getElementById('t-due').value                  = t.due_date;
    document.getElementById('t-completed').checked          = t.is_completed;
    resetTaskFileField(t);
    openModal('modal-task');
}

async function saveTask() {
    const v1 = validateRequired('t-course', 'err-t-course');
    const v2 = validateRequired('t-title',  'err-t-title');
    const v3 = validateRequired('t-due',    'err-t-due');
    if (!v1 || !v2 || !v3) return;

    const data = {
        course_id:    parseInt(document.getElementById('t-course').value),
        title:        document.getElementById('t-title').value.trim(),
        type:         document.getElementById('t-type').value,
        priority:     document.getElementById('t-priority').value,
        due_date:     document.getElementById('t-due').value,
        is_completed: document.getElementById('t-completed').checked,
    };

    const editId = document.getElementById('t-edit-id').value;
    const btn    = document.querySelector('#modal-task .btn-primary');
    setLoading(btn, true, 'Saving…');

    try {
        Object.assign(data, await uploadTaskFileIfAny());
        if (editId) {
            const updated = await TasksAPI.update(editId, data);
            allTasks = allTasks.map(t => t.id === parseInt(editId) ? updated : t);
            showToast('Task updated.', 'success');
        } else {
            const created = await TasksAPI.create(data);
            allTasks.push(created);
            showToast('Task added.', 'success');
        }
        closeModal('modal-task');
        renderTasks();
    } catch (err) {
        showToast(err.message || 'Failed to save task.', 'error');
    } finally {
        setLoading(btn, false);
    }
}

function promptDeleteTask(id) {
    document.getElementById('delete-task-id').value = id;
    openModal('modal-delete');
}

async function confirmDeleteTask() {
    const id  = parseInt(document.getElementById('delete-task-id').value);
    const btn = document.querySelector('#modal-delete .btn-danger');
    setLoading(btn, true, 'Deleting…');
    try {
        await TasksAPI.remove(id);
        allTasks = allTasks.filter(t => t.id !== id);
        closeModal('modal-delete');
        renderTasks();
        showToast('Task deleted.');
    } catch (err) {
        showToast('Failed to delete task.', 'error');
    } finally {
        setLoading(btn, false);
    }
}

/*  SYLLABUS IMPORT  */

let proposedTasks = [];

function openSyllabusModal() {
    if (allCourses.length === 0) {
        showToast('Add a course before importing a syllabus.', 'error');
        return;
    }

    document.getElementById('s-course').innerHTML =
        '<option value="">— Select a course —</option>' +
        allCourses.map(c => `<option value="${c.id}">${escapeHtml(c.code)} — ${escapeHtml(c.name)}</option>`).join('');
    document.getElementById('s-text').value = '';
    document.getElementById('s-file').value = '';

    backToSyllabusInput();
    document.getElementById('syllabus-error').classList.remove('show');
    document.querySelectorAll('#modal-syllabus .form-error').forEach(e => e.classList.remove('show'));
    openModal('modal-syllabus');
}

function backToSyllabusInput() {
    document.getElementById('syllabus-input-step').classList.remove('hidden');
    document.getElementById('syllabus-result-step').classList.add('hidden');
    document.getElementById('syllabus-analyze-btn').classList.remove('hidden');
    document.getElementById('syllabus-import-btn').classList.add('hidden');
    document.getElementById('syllabus-back-btn').classList.add('hidden');
}

async function loadSyllabusFile(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 200 * 1024) {
        showToast('File too large — 200 KB max.', 'error');
        return;
    }

    try {
        document.getElementById('s-text').value = (await file.text()).slice(0, 40000);
    } catch (err) {
        showToast('Could not read that file.', 'error');
    } finally {
        e.target.value = '';
    }
}

async function analyzeSyllabus() {
    const errBox = document.getElementById('syllabus-error');
    errBox.classList.remove('show');

    const v1   = validateRequired('s-course', 'err-s-course');
    const text = document.getElementById('s-text').value.trim();
    const v2   = text.length >= 40;

    document.getElementById('err-s-text').classList.toggle('show', !v2);
    document.getElementById('s-text').style.borderColor = v2 ? '' : 'var(--red)';
    if (!v1 || !v2) return;

    const btn = document.getElementById('syllabus-analyze-btn');
    setLoading(btn, true, 'Reading…');

    try {
        const r = await AiAPI.syllabus(parseInt(document.getElementById('s-course').value), text);
        proposedTasks = r.tasks || [];
        renderProposedTasks();
    } catch (err) {
        errBox.textContent = err.message || 'Could not read that syllabus.';
        errBox.classList.add('show');
    } finally {
        setLoading(btn, false);
    }
}

function renderProposedTasks() {
    const list  = document.getElementById('syllabus-list');
    const found = document.getElementById('syllabus-found');

    document.getElementById('syllabus-input-step').classList.add('hidden');
    document.getElementById('syllabus-result-step').classList.remove('hidden');
    document.getElementById('syllabus-analyze-btn').classList.add('hidden');
    document.getElementById('syllabus-back-btn').classList.remove('hidden');

    if (proposedTasks.length === 0) {
        found.textContent = 'Nothing found';
        document.getElementById('syllabus-import-btn').classList.add('hidden');
        list.innerHTML = `
            <div class="empty-state" style="padding:30px 20px">
                <h4>No dated work found</h4>
                <p>The text needs a schedule with real dates. Try pasting the week-by-week section.</p>
            </div>`;
        return;
    }

    found.textContent = `${proposedTasks.length} item${proposedTasks.length > 1 ? 's' : ''} found — untick anything you don't want`;
    document.getElementById('syllabus-import-btn').classList.remove('hidden');

    list.innerHTML = proposedTasks.map((t, i) => `
        <label class="proposed-row" for="prop-${i}">
            <input type="checkbox" id="prop-${i}" class="task-checkbox" checked/>
            <div class="task-info">
                <div class="task-title">${escapeHtml(t.title)}</div>
                <div class="task-meta">
                    <span class="text-sm text-muted">${t.type}</span>
                    <span class="text-sm text-muted">·</span>
                    <span class="text-sm text-muted">Due ${formatDate(t.due_date)}</span>
                </div>
            </div>
            <span class="badge ${PRIORITY_BADGE[t.priority] || 'badge-gray'}">${t.priority}</span>
        </label>`).join('');
}

function toggleAllProposed() {
    const boxes = document.querySelectorAll('#syllabus-list input[type="checkbox"]');
    const allOn = [...boxes].every(b => b.checked);
    boxes.forEach(b => { b.checked = !allOn; });
}

async function importProposedTasks() {
    const courseId = parseInt(document.getElementById('s-course').value);
    const picked   = proposedTasks.filter((_, i) => document.getElementById('prop-' + i).checked);

    if (picked.length === 0) {
        showToast('Nothing selected.', 'error');
        return;
    }

    const btn = document.getElementById('syllabus-import-btn');
    setLoading(btn, true, 'Adding…');

    let added = 0;
    for (const t of picked) {
        try {
            allTasks.push(await TasksAPI.create({ ...t, course_id: courseId, is_completed: false }));
            added++;
        } catch (err) { /* keep going; the total is reported below */ }
    }

    setLoading(btn, false);
    closeModal('modal-syllabus');
    renderTasks();

    if (added === picked.length) {
        showToast(`Added ${added} task${added > 1 ? 's' : ''}.`, 'success');
    } else {
        showToast(`Added ${added} of ${picked.length}. Some could not be saved.`, 'error');
    }
}
