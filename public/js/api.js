

// Relative path so it works on any host/port (artisan serve, Apache, etc.)
const API_BASE = '/api';

function getToken()    { return localStorage.getItem('um_token') || null; }
function setToken(t)   { localStorage.setItem('um_token', t); }
function removeToken() { localStorage.removeItem('um_token'); localStorage.removeItem('um_user'); }

function saveUser(u) { localStorage.setItem('um_user', JSON.stringify(u)); }
function getUser()   { try { return JSON.parse(localStorage.getItem('um_user')); } catch { return null; } }
function clearUser() { localStorage.removeItem('um_user'); localStorage.removeItem('um_token'); }

function requireAuth() {
    if (!getToken()) {
        window.location.replace('/login');
        return false;
    }
    return true;
}

async function logout() {
    if (!confirm('Log out of UniMate?')) return;
    try { await apiFetch('/auth/logout', { method: 'POST' }); } catch (e) { /* token cleared below anyway */ }
    clearUser();
    window.location.replace('/login');
}

function setLoading(btn, on, text) {
    if (!btn) return;
    if (on) { btn.dataset.orig = btn.textContent; btn.textContent = text || 'Loading…'; btn.disabled = true; }
    else    { btn.textContent = btn.dataset.orig || btn.textContent; btn.disabled = false; }
}

async function apiFetch(endpoint, options = {}) {
    const token = getToken();
    const config = {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
            ...(options.headers || {}),
        },
    };
    if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }
    try {
        const res  = await fetch(API_BASE + endpoint, config);
        if (res.status === 401) {
            removeToken();
            window.location.replace('/login');
            return null;
        }
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Request failed.');
            const err = new Error(msg);
            err.status = res.status;
            err.errors = data.errors || null;
            throw err;
        }
        return data;
    } catch (err) {
        if (err.status) throw err;
        throw new Error('Network error. Make sure Laravel is running: php artisan serve');
    }
}

const Auth = {
    async register(data) {
        const r = await apiFetch('/auth/register', { method: 'POST', body: data });
        if (r) { setToken(r.token); saveUser(r.user); }
        return r;
    },
    async login(uid, pw) {
        const r = await apiFetch('/auth/login', { method: 'POST', body: { university_id: uid, password: pw } });
        if (r) { setToken(r.token); saveUser(r.user); }
        return r;
    },
    logout() { return apiFetch('/auth/logout', { method: 'POST' }).catch(() => {}); },
    me()     { return apiFetch('/auth/me').then(r => { if (r) saveUser(r.user); return r; }); },
};

const CoursesAPI = {
    list()        { return apiFetch('/courses'); },
    create(d)     { return apiFetch('/courses',       { method: 'POST',   body: d }); },
    update(id, d) { return apiFetch('/courses/' + id, { method: 'PUT',    body: d }); },
    remove(id)    { return apiFetch('/courses/' + id, { method: 'DELETE' }); },
};

const TasksAPI = {
    list()        { return apiFetch('/tasks'); },
    create(d)     { return apiFetch('/tasks',                   { method: 'POST',   body: d }); },
    update(id, d) { return apiFetch('/tasks/' + id,             { method: 'PUT',    body: d }); },
    toggle(id)    { return apiFetch('/tasks/' + id + '/toggle', { method: 'PATCH' }); },
    remove(id)    { return apiFetch('/tasks/' + id,             { method: 'DELETE' }); },
};

const LecturesAPI = {
    list()        { return apiFetch('/lectures'); },
    create(d)     { return apiFetch('/lectures',       { method: 'POST',   body: d }); },
    update(id, d) { return apiFetch('/lectures/' + id, { method: 'PUT',    body: d }); },
    remove(id)    { return apiFetch('/lectures/' + id, { method: 'DELETE' }); },
};

const ResourcesAPI = {
    list()        { return apiFetch('/resources'); },
    create(d)     { return apiFetch('/resources',       { method: 'POST',   body: d }); },
    update(id, d) { return apiFetch('/resources/' + id, { method: 'PUT',    body: d }); },
    remove(id)    { return apiFetch('/resources/' + id, { method: 'DELETE' }); },
};

// For multipart uploads — the browser sets the Content-Type boundary itself
async function apiUpload(endpoint, formData) {
    const token = getToken();
    const res = await fetch(API_BASE + endpoint, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
        },
        body: formData,
    });
    if (res.status === 401) {
        removeToken();
        window.location.replace('/login');
        return null;
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Upload failed.');
        throw new Error(msg);
    }
    return data;
}

const UploadsAPI = {
    send(file) {
        const fd = new FormData();
        fd.append('file', file);
        return apiUpload('/uploads', fd);
    },
};

const ProfileAPI = {
    update(d) { return apiFetch('/auth/profile', { method: 'PUT', body: d }); },
    avatar(file) {
        const fd = new FormData();
        fd.append('avatar', file);
        return apiUpload('/auth/avatar', fd);
    },
};

const MessagesAPI = {
    contacts()         { return apiFetch('/chat/contacts'); },
    conversation(id)   { return apiFetch('/chat/messages/' + id); },
    send(id, body)     { return apiFetch('/chat/messages/' + id, { method: 'POST', body: { body } }); },
};

const GroupsAPI = {
    list()             { return apiFetch('/groups'); },
    messages(code)     { return apiFetch('/groups/' + encodeURIComponent(code) + '/messages'); },
    send(code, body)   { return apiFetch('/groups/' + encodeURIComponent(code) + '/messages', { method: 'POST', body: { body } }); },
};

const SharedResourcesAPI = {
    list(courseId)     { return apiFetch('/shared-resources?course_id=' + courseId); },
};

const AiAPI = {
    status()       { return apiFetch('/ai/status'); },
    chat(messages) { return apiFetch('/ai/chat', { method: 'POST', body: { messages } }); },
    syllabus(courseId, text)   { return apiFetch('/ai/syllabus', { method: 'POST', body: { course_id: courseId, text } }); },
    syllabusFile(courseId, file) {
        const fd = new FormData();
        fd.append('course_id', courseId);
        fd.append('file', file);
        return apiUpload('/ai/syllabus', fd);
    },
};

const ChatAPI = {
    list()        { return apiFetch('/chat-sessions'); },
    create(d)     { return apiFetch('/chat-sessions',       { method: 'POST',   body: d }); },
    update(id, d) { return apiFetch('/chat-sessions/' + id, { method: 'PUT',    body: d }); },
    remove(id)    { return apiFetch('/chat-sessions/' + id, { method: 'DELETE' }); },
};
