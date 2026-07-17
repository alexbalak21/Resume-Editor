(function () {
    'use strict';

    let state = null;
    let currentProfile = null;
    let renderTimer = null;
    let dirty = false;

    const form = document.getElementById('cvForm');
    const profileSelect = document.getElementById('profileSelect');
    const previewFrame = document.getElementById('previewFrame');
    const saveStatus = document.getElementById('saveStatus');
    const saveBtn = document.getElementById('saveBtn');
    const newProfileBtn = document.getElementById('newProfileBtn');

    // ---------- path helpers ----------
    function getPath(obj, path) {
        return path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj);
    }
    function setPath(obj, path, value) {
        const keys = path.split('.');
        let cur = obj;
        for (let i = 0; i < keys.length - 1; i++) {
            if (cur[keys[i]] == null) cur[keys[i]] = {};
            cur = cur[keys[i]];
        }
        cur[keys[keys.length - 1]] = value;
    }

    function markDirty() {
        dirty = true;
        saveStatus.textContent = 'Non enregistré';
        saveStatus.style.color = '#fbbf24';
    }
    function markSaved() {
        dirty = false;
        saveStatus.textContent = 'Enregistré';
        saveStatus.style.color = '#86efac';
        setTimeout(() => { if (!dirty) saveStatus.textContent = ''; }, 2000);
    }

    // ---------- API ----------
    async function apiGet(action, params) {
        const qs = new URLSearchParams({ action, ...params });
        const res = await fetch('api.php?' + qs.toString());
        return res.json();
    }
    async function apiPost(action, body) {
        const res = await fetch('api.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    // ---------- preview ----------
    function scheduleRender() {
        clearTimeout(renderTimer);
        renderTimer = setTimeout(renderPreview, 250);
    }

    async function renderPreview() {
        const result = await apiPost('render', { data: state });
        const doc = `<!DOCTYPE html><html><head><meta charset="UTF-8">
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
            <link rel="stylesheet" href="assets/template.css">
            </head><body>${result.html || ''}</body></html>`;
        previewFrame.srcdoc = doc;
    }

    // ---------- static field binding ----------
    function bindStaticFields() {
        form.querySelectorAll('[data-path]').forEach((el) => {
            const path = el.getAttribute('data-path');
            el.value = getPath(state, path) ?? '';
            el.oninput = () => {
                setPath(state, path, el.value);
                markDirty();
                scheduleRender();
            };
        });
    }

    // ---------- repeat-list (structured rows: e.g. contact items, links, languages) ----------
    function buildRepeatLists() {
        form.querySelectorAll('.repeat-list').forEach((container) => {
            const path = container.getAttribute('data-list');
            const fields = container.getAttribute('data-fields').split(',');
            const placeholders = container.getAttribute('data-placeholders').split(',');
            renderRepeatList(container, path, fields, placeholders);
        });
    }

    function renderRepeatList(container, path, fields, placeholders) {
        container.innerHTML = '';
        const arr = getPath(state, path) || [];
        arr.forEach((item, idx) => {
            const row = document.createElement('div');
            row.className = 'repeat-row';
            fields.forEach((f, fi) => {
                const input = document.createElement('input');
                input.type = 'text';
                input.placeholder = placeholders[fi] || '';
                input.value = item[f] || '';
                input.oninput = () => {
                    arr[idx][f] = input.value;
                    markDirty();
                    scheduleRender();
                };
                row.appendChild(input);
            });
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'remove-btn';
            rm.innerHTML = '<i class="fa-solid fa-trash"></i>';
            rm.onclick = () => {
                arr.splice(idx, 1);
                markDirty();
                renderRepeatList(container, path, fields, placeholders);
                scheduleRender();
            };
            row.appendChild(rm);
            container.appendChild(row);
        });
    }

    // ---------- repeat-simple (plain string list: skills, certifications, hobbies) ----------
    function buildRepeatSimples() {
        form.querySelectorAll('.repeat-simple').forEach((container) => {
            const path = container.getAttribute('data-list');
            const placeholder = container.getAttribute('data-placeholder') || '';
            renderRepeatSimple(container, path, placeholder);
        });
    }

    function renderRepeatSimple(container, path, placeholder) {
        container.innerHTML = '';
        const arr = getPath(state, path) || [];
        arr.forEach((val, idx) => {
            const row = document.createElement('div');
            row.className = 'repeat-row';
            const input = document.createElement('input');
            input.type = 'text';
            input.placeholder = placeholder;
            input.value = val || '';
            input.oninput = () => {
                arr[idx] = input.value;
                markDirty();
                scheduleRender();
            };
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'remove-btn';
            rm.innerHTML = '<i class="fa-solid fa-trash"></i>';
            rm.onclick = () => {
                arr.splice(idx, 1);
                markDirty();
                renderRepeatSimple(container, path, placeholder);
                scheduleRender();
            };
            row.appendChild(input);
            row.appendChild(rm);
            container.appendChild(row);
        });
    }

    // ---------- timeline (experience / education entries) ----------
    function buildTimelines() {
        form.querySelectorAll('.repeat-timeline').forEach((container) => {
            const path = container.getAttribute('data-list');
            renderTimeline(container, path);
        });
    }

    function renderTimeline(container, path) {
        container.innerHTML = '';
        const arr = getPath(state, path) || [];
        arr.forEach((item, idx) => {
            const card = document.createElement('div');
            card.className = 'timeline-card';

            const head = document.createElement('div');
            head.className = 'timeline-card-head';
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'remove-btn';
            rm.innerHTML = '<i class="fa-solid fa-trash"></i>';
            rm.onclick = () => {
                arr.splice(idx, 1);
                markDirty();
                renderTimeline(container, path);
                scheduleRender();
            };
            head.appendChild(rm);
            card.appendChild(head);

            const titleLabel = document.createElement('label');
            titleLabel.textContent = 'Intitulé';
            const titleInput = document.createElement('input');
            titleInput.type = 'text';
            titleInput.placeholder = 'ex: Développeur Full-Stack - Novocib';
            titleInput.value = item.title || '';
            titleInput.oninput = () => { item.title = titleInput.value; markDirty(); scheduleRender(); };

            const metaLabel = document.createElement('label');
            metaLabel.textContent = 'Dates / Lieu';
            const metaInput = document.createElement('input');
            metaInput.type = 'text';
            metaInput.placeholder = 'ex: Jul 2025 – Feb 2026';
            metaInput.value = item.meta || '';
            metaInput.oninput = () => { item.meta = metaInput.value; markDirty(); scheduleRender(); };

            const bulletsLabel = document.createElement('label');
            bulletsLabel.textContent = 'Points clés';
            const bulletsList = document.createElement('div');
            bulletsList.className = 'bullets-list';
            if (!item.bullets) item.bullets = [];

            function renderBullets() {
                bulletsList.innerHTML = '';
                item.bullets.forEach((b, bi) => {
                    const row = document.createElement('div');
                    row.className = 'repeat-row';
                    const input = document.createElement('textarea');
                    input.rows = 1;
                    input.value = b || '';
                    input.oninput = () => { item.bullets[bi] = input.value; markDirty(); scheduleRender(); };
                    const rmB = document.createElement('button');
                    rmB.type = 'button';
                    rmB.className = 'remove-btn';
                    rmB.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    rmB.onclick = () => { item.bullets.splice(bi, 1); markDirty(); renderBullets(); scheduleRender(); };
                    row.appendChild(input);
                    row.appendChild(rmB);
                    bulletsList.appendChild(row);
                });
            }
            renderBullets();

            const addBulletBtn = document.createElement('button');
            addBulletBtn.type = 'button';
            addBulletBtn.className = 'add-btn';
            addBulletBtn.textContent = '+ Ajouter un point';
            addBulletBtn.onclick = () => { item.bullets.push(''); markDirty(); renderBullets(); scheduleRender(); };

            card.appendChild(titleLabel);
            card.appendChild(titleInput);
            card.appendChild(metaLabel);
            card.appendChild(metaInput);
            card.appendChild(bulletsLabel);
            card.appendChild(bulletsList);
            card.appendChild(addBulletBtn);

            container.appendChild(card);
        });
    }

    // ---------- add buttons ----------
    function bindAddButtons() {
        form.querySelectorAll('[data-add]').forEach((btn) => {
            btn.onclick = () => {
                const path = btn.getAttribute('data-add');
                const arr = getPath(state, path) || [];
                const container = form.querySelector(`.repeat-list[data-list="${path}"], .repeat-simple[data-list="${path}"]`);
                if (container.classList.contains('repeat-list')) {
                    const fields = container.getAttribute('data-fields').split(',');
                    const empty = {};
                    fields.forEach((f) => (empty[f] = ''));
                    arr.push(empty);
                } else {
                    arr.push('');
                }
                setPath(state, path, arr);
                markDirty();
                rebuildDynamicSections();
                scheduleRender();
            };
        });

        form.querySelectorAll('[data-add-timeline]').forEach((btn) => {
            btn.onclick = () => {
                const path = btn.getAttribute('data-add-timeline');
                const arr = getPath(state, path) || [];
                arr.push({ title: '', meta: '', bullets: [] });
                setPath(state, path, arr);
                markDirty();
                rebuildDynamicSections();
                scheduleRender();
            };
        });
    }

    function rebuildDynamicSections() {
        buildRepeatLists();
        buildRepeatSimples();
        buildTimelines();
    }

    // ---------- profile loading ----------
    function fillForm() {
        bindStaticFields();
        rebuildDynamicSections();
    }

    async function loadProfile(name) {
        const result = await apiGet('load', { profile: name });
        if (result.error) {
            alert('Erreur de chargement : ' + result.error);
            return;
        }
        currentProfile = name;
        state = result.data;
        fillForm();
        renderPreview();
        saveStatus.textContent = '';
        dirty = false;
    }

    async function refreshProfileList(selectName) {
        const result = await apiGet('list', {});
        profileSelect.innerHTML = '';
        (result.profiles || []).forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = p;
            profileSelect.appendChild(opt);
        });
        if (selectName) profileSelect.value = selectName;
    }

    // ---------- events ----------
    profileSelect.addEventListener('change', () => {
        if (dirty && !confirm('Des modifications ne sont pas enregistrées. Changer de profil quand même ?')) {
            profileSelect.value = currentProfile;
            return;
        }
        loadProfile(profileSelect.value);
    });

    saveBtn.addEventListener('click', async () => {
        saveStatus.textContent = 'Enregistrement...';
        saveStatus.style.color = '#9ca3af';
        const result = await apiPost('save', { profile: currentProfile, data: state });
        if (result.ok) {
            markSaved();
        } else {
            saveStatus.textContent = 'Erreur';
            saveStatus.style.color = '#f87171';
        }
    });

    newProfileBtn.addEventListener('click', async () => {
        const name = prompt('Nom du nouveau profil (ex: freelance, manager...) :');
        if (!name) return;
        const safe = name.trim().toLowerCase().replace(/[^a-z0-9_\-]/g, '_');
        const result = await apiPost('create', { profile: safe });
        if (result.error) {
            alert('Erreur : ' + result.error);
            return;
        }
        await refreshProfileList(safe);
        await loadProfile(safe);
    });

    // ---------- init ----------
    (async function init() {
        const result = await apiGet('list', {});
        const profiles = result.profiles || [];
        await refreshProfileList();
        const first = profiles.includes('developer') ? 'developer' : profiles[0];
        if (first) {
            profileSelect.value = first;
            await loadProfile(first);
        }
    })();
})();
