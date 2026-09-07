

/*  STATE  */

let allLectures = [];
let allCourses  = [];

const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const DAY_SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const SLOT      = 30; // minutes per grid row

/*  INIT  */

document.addEventListener('DOMContentLoaded', async () => {
    if (!requireAuth()) return;
    renderSidebar('timetable.html');
    await loadData();
});

async function loadData() {
    try {
        const [lectures, courses] = await Promise.all([LecturesAPI.list(), CoursesAPI.list()]);
        allLectures = lectures;
        allCourses  = courses;
        populateCourseSelect();
        renderTimetable();
    } catch (err) {
        showToast('Failed to load your timetable.', 'error');
    }
}

function populateCourseSelect() {
    document.getElementById('l-course').innerHTML =
        '<option value="">— Select a course —</option>' +
        allCourses.map(c => `<option value="${c.id}">${escapeHtml(c.code)} — ${escapeHtml(c.name)}</option>`).join('');
}

/*  TIME HELPERS  */

function toMinutes(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
}

function toLabel(minutes) {
    const h      = Math.floor(minutes / 60);
    const m      = minutes % 60;
    const suffix = h < 12 ? 'AM' : 'PM';
    const h12    = h % 12 === 0 ? 12 : h % 12;
    return `${h12}:${String(m).padStart(2, '0')} ${suffix}`;
}

/*  RENDER  */

function emptyState(icon, title, text) {
    return `<div class="empty-state">${icon}<h4>${title}</h4><p>${text}</p></div>`;
}

function renderTimetable() {
    const grid = document.getElementById('tt-grid');
    const sub  = document.getElementById('tt-subtitle');

    const calendarIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    const clockIcon    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

    if (allCourses.length === 0) {
        grid.className = '';
        grid.removeAttribute('style');
        grid.innerHTML = emptyState(calendarIcon, 'Add a course first',
            "Your timetable is built from the courses you're enrolled in.");
        sub.textContent = 'Your lecture schedule';
        return;
    }

    if (allLectures.length === 0) {
        grid.className = '';
        grid.removeAttribute('style');
        grid.innerHTML = emptyState(clockIcon, 'No classes yet',
            'Add your weekly lecture times with the button above.');
        sub.textContent = 'Your lecture schedule';
        return;
    }

    // Weekdays always show; weekend days only when something is scheduled
    const used = new Set(allLectures.map(l => l.day_of_week));
    const days = [0, 1, 2, 3, 4].concat([5, 6].filter(d => used.has(d)));

    // The grid spans whole hours around the earliest and latest class
    const from = Math.floor(Math.min(...allLectures.map(l => toMinutes(l.start_time))) / 60) * 60;
    const to   = Math.ceil(Math.max(...allLectures.map(l => toMinutes(l.end_time))) / 60) * 60;
    const rows = (to - from) / SLOT;

    grid.className = 'tt-grid';
    grid.style.gridTemplateColumns = `72px repeat(${days.length}, minmax(120px, 1fr))`;
    grid.style.gridTemplateRows    = `auto repeat(${rows}, 22px)`;

    let html = '<div class="tt-corner"></div>';

    days.forEach((d, i) => {
        html += `<div class="tt-day-head" style="grid-column:${i + 2};grid-row:1">
                    <span class="tt-day-full">${DAY_NAMES[d]}</span><span class="tt-day-short">${DAY_SHORT[d]}</span>
                 </div>`;
    });

    // Hour labels plus a guide line across the week
    for (let m = from; m < to; m += 60) {
        const row = 2 + (m - from) / SLOT;
        html += `<div class="tt-hour" style="grid-row:${row} / span 2">${toLabel(m)}</div>`;
        html += `<div class="tt-line" style="grid-row:${row};grid-column:2 / span ${days.length}"></div>`;
    }

    // ponytail: classes overlapping on the same day stack on top of each other.
    // Split the column into lanes if clashing timetables ever need showing.
    allLectures.forEach(l => {
        const col = days.indexOf(l.day_of_week);
        if (col === -1) return;

        const start = toMinutes(l.start_time);
        const span  = Math.max(1, (toMinutes(l.end_time) - start) / SLOT);
        const row   = 2 + (start - from) / SLOT;
        const code  = l.course ? l.course.code : 'Class';
        const name  = l.course ? l.course.name : '';
        const tip   = `${code}${name ? ' — ' + name : ''} · ${l.start_time}–${l.end_time}${l.room ? ' · ' + l.room : ''}`;

        html += `
            <div class="tt-class" style="grid-column:${col + 2};grid-row:${row} / span ${span}"
                 onclick="openEditLecture(${l.id})" title="${escapeHtml(tip)}">
                <div class="tt-class-code">${escapeHtml(code)}</div>
                <div class="tt-class-time">${l.start_time}–${l.end_time}</div>
                ${l.room ? `<div class="tt-class-room">${escapeHtml(l.room)}</div>` : ''}
            </div>`;
    });

    grid.innerHTML = html;
    sub.textContent = `${allLectures.length} class${allLectures.length > 1 ? 'es' : ''} a week`;
}

/*  ADD / EDIT  */

function openAddLecture() {
    if (allCourses.length === 0) {
        showToast('Add a course before scheduling classes.', 'error');
        return;
    }
    document.getElementById('lecture-modal-title').textContent = 'Add Class';
    document.getElementById('l-edit-id').value = '';
    document.getElementById('l-course').value  = '';
    document.getElementById('l-day').value     = '0';
    document.getElementById('l-start').value   = '09:00';
    document.getElementById('l-end').value     = '10:30';
    document.getElementById('l-room').value    = '';
    document.getElementById('l-delete-btn').classList.add('hidden');
    resetLectureErrors();
    openModal('modal-lecture');
}

function openEditLecture(id) {
    const l = allLectures.find(x => x.id === id);
    if (!l) return;
    document.getElementById('lecture-modal-title').textContent = 'Edit Class';
    document.getElementById('l-edit-id').value = l.id;
    document.getElementById('l-course').value  = l.course_id;
    document.getElementById('l-day').value     = l.day_of_week;
    document.getElementById('l-start').value   = l.start_time;
    document.getElementById('l-end').value     = l.end_time;
    document.getElementById('l-room').value    = l.room || '';
    document.getElementById('l-delete-btn').classList.remove('hidden');
    resetLectureErrors();
    openModal('modal-lecture');
}

function resetLectureErrors() {
    document.getElementById('lecture-error').classList.remove('show');
    document.querySelectorAll('#modal-lecture .form-error').forEach(e => e.classList.remove('show'));
    document.querySelectorAll('#modal-lecture .form-control').forEach(e => e.style.borderColor = '');
}

async function saveLecture() {
    const v1 = validateRequired('l-course', 'err-l-course');
    const v2 = validateRequired('l-start',  'err-l-start');
    const v3 = validateRequired('l-end',    'err-l-end');
    if (!v1 || !v2 || !v3) return;

    const data = {
        course_id:   parseInt(document.getElementById('l-course').value),
        day_of_week: parseInt(document.getElementById('l-day').value),
        start_time:  document.getElementById('l-start').value,
        end_time:    document.getElementById('l-end').value,
        room:        document.getElementById('l-room').value.trim() || null,
    };

    const editId = document.getElementById('l-edit-id').value;
    const btn    = document.querySelector('#modal-lecture .btn-primary');
    setLoading(btn, true, 'Saving…');

    try {
        if (editId) {
            const updated = await LecturesAPI.update(editId, data);
            allLectures = allLectures.map(l => l.id === parseInt(editId) ? updated : l);
            showToast('Class updated.', 'success');
        } else {
            allLectures.push(await LecturesAPI.create(data));
            showToast('Class added.', 'success');
        }
        closeModal('modal-lecture');
        renderTimetable();
    } catch (err) {
        const box = document.getElementById('lecture-error');
        box.textContent = err.message || 'Failed to save the class.';
        box.classList.add('show');
    } finally {
        setLoading(btn, false);
    }
}

async function deleteLecture() {
    const id = parseInt(document.getElementById('l-edit-id').value);
    if (!id || !confirm('Remove this class from your timetable?')) return;

    const btn = document.getElementById('l-delete-btn');
    setLoading(btn, true, 'Removing…');

    try {
        await LecturesAPI.remove(id);
        allLectures = allLectures.filter(l => l.id !== id);
        closeModal('modal-lecture');
        renderTimetable();
        showToast('Class removed.');
    } catch (err) {
        showToast('Failed to remove the class.', 'error');
    } finally {
        setLoading(btn, false);
    }
}
