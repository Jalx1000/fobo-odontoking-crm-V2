<script type="text/x-template" id="v-doctor-multiselect-template">
    <div class="ms-container" ref="root">
        <div class="ms-input" @click="open=!open">
            <div class="ms-chips">
                <span class="ms-chip" v-for="sid in model" :key="'chip-'+sid">
                    @{{ nameById(sid) }}
                    <i class="icon-cross-large ms-chip-x" @click.stop="remove(sid)"></i>
                </span>
            </div>
            <div class="ms-actions">
                <span class="ms-count">@{{ model.length }}</span>
                <i :class="open ? 'icon-up-arrow' : 'icon-down-arrow'" class="text-xl"></i>
            </div>
        </div>
        <div v-if="open" class="ms-dropdown">
            <input type="text" v-model="q" class="ms-search" placeholder="Buscar" />
            <ul class="ms-list">
                <li v-for="d in filtered" :key="'li-'+d.id" class="ms-item" @click="toggle(String(d.id))">
                    <input type="checkbox" :checked="set.has(String(d.id))" />
                    <span>@{{ d.name }}</span>
                </li>
                <li v-if="!filtered.length" class="ms-empty">Sin resultados</li>
            </ul>
        </div>
    </div>
</script>

<script type="text/x-template" id="v-doctor-week-calendar-template">
    <div class="dwc-container">
        <div class="dwc-controls">
            <div class="flex items-center gap-2">
                <button class="px-2 py-1 rounded border dark:border-gray-800" @click="prevWeek">←</button>
                <button class="px-2 py-1 rounded border dark:border-gray-800" @click="goThisWeek">Esta semana</button>
                <button class="px-2 py-1 rounded border dark:border-gray-800" @click="nextWeek">→</button>
                <span class="text-sm font-semibold dark:text-white">@{{ weekLabel }}</span>
            </div>

            <div class="dwc-filters">
                <span class="text-xs dark:text-gray-300">Filtrar doctores:</span>
                <button type="button" class="px-2 py-1 rounded border text-xs dark:border-gray-800" @click="selectAllDoctors">Todos</button>
                <button type="button" class="px-2 py-1 rounded border text-xs dark:border-gray-800" @click="clearDoctors">Ninguno</button>
                <v-doctor-multiselect :items="doctors" v-model="selectedDoctorIds"></v-doctor-multiselect>
            </div>
        </div>

        <div class="dwc-grid" :style="{ gridTemplateColumns: '80px repeat(' + columns.length + ', 1fr)' }">
            <div class="dwc-hours">
                <div v-for="h in 24" :key="'hr-'+h" class="dwc-hour-row" :style="{ height: hourHeight + 'px' }">@{{ pad2(h-1) }}:00</div>
            </div>

            <div v-for="col in columns" :key="'col-'+col.id" class="dwc-doctor-col">
                <div class="dwc-doctor-header">
                    <span>@{{ col.name }}</span>
                    <span class="text-xs dark:text-gray-300">@{{ totalCount(col.id) }}</span>
                </div>

                <div class="dwc-days-stack" :style="{ height: totalHeight + 'px' }" @click="onColumnClick($event, col.id)">
                    <div v-for="(day, di) in days" :key="'day-'+di" class="dwc-day-block" :style="{ height: dayHeight + 'px' }">
                        <div v-for="idx in 24" :key="'line-'+di+'-'+idx" class="dwc-hour-line" :style="{ top: ((idx - 1) * hourHeight) + 'px' }"></div>

                        <div v-for="ev in dayDoctorEvents(day.date, col.id)" :key="'ev-'+ev.id" class="dwc-event" :style="{ top: ev.top + 'px', height: ev.height + 'px' }">
                            <div class="dwc-event-title">@{{ ev.title || ev.type }}</div>
                            <div class="dwc-event-time">@{{ formatTime(ev.start) }} — @{{ formatTime(ev.end) }} · @{{ day.label }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <a :href="editUrl(ev.id)" class="icon-edit text-xl"></a>
                                <button type="button" class="icon-delete text-xl text-red-600" @click.stop="remove(ev)"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="addForm.visible" class="dwc-add-overlay" :style="{ top: addForm.top + 'px', left: addForm.left + 'px', width: overlayWidth + 'px' }">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs dark:text-gray-300">@{{ addForm.dayLabel }}</span>
                <span class="text-xs dark:text-gray-300">·</span>
                <span class="text-xs dark:text-gray-300">@{{ addForm.doctorLabel }}</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <input type="text" class="rounded border px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" v-model="addForm.title" placeholder="Título" />
                <select class="rounded border px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" v-model="addForm.type">
                    <option value="meeting">Reunión</option>
                    <option value="call">Llamada</option>
                    <option value="lunch">Almuerzo</option>
                </select>
                <input type="time" class="rounded border px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" v-model="addForm.startTime" />
                <input type="time" class="rounded border px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" v-model="addForm.endTime" />
            </div>

            <div class="mt-2 flex items-center gap-2">
                <button type="button" class="secondary-button" @click="cancelAdd">Cancelar</button>
                <button type="button" class="primary-button" @click="saveAdd" :disabled="isSaving">
                    <span v-if="!isSaving">Guardar</span>
                    <span v-else class="flex items-center gap-2"><x-admin::spinner /> Guardando...</span>
                </button>
            </div>

            <div class="mt-1 text-sm text-red-600" v-if="addError">@{{ addError }}</div>
        </div>
    </div>
</script>

<script type="module">
    app.component('v-doctor-multiselect', {
        template: '#v-doctor-multiselect-template',
        props: {
            items: { type: Array, default: () => [] },
            modelValue: { type: Array, default: () => [] },
        },
        emits: ['update:modelValue'],
        data() { return { open: false, q: '' }; },
        computed: {
            model() { return this.modelValue; },
            set() { return new Set(this.model); },
            filtered() {
                const q = this.q.trim().toLowerCase();
                return q
                    ? this.items.filter(d => d && String(d.name || '').toLowerCase().includes(q))
                    : this.items.filter(d => d);
            },
        },
        methods: {
            nameById(id) {
                const d = this.items.find(x => x && String(x.id) === String(id));
                return d ? d.name : '';
            },
            toggle(id) {
                const next = new Set(this.model);
                if (next.has(id)) next.delete(id);
                else next.add(id);
                this.$emit('update:modelValue', Array.from(next));
            },
            remove(id) {
                const next = this.model.filter(x => String(x) !== String(id));
                this.$emit('update:modelValue', next);
            },
            onClickOutside(e) {
                const root = this.$refs.root;
                if (root && !root.contains(e.target)) this.open = false;
            },
        },
        mounted() { window.addEventListener('click', this.onClickOutside); },
        beforeUnmount() { window.removeEventListener('click', this.onClickOutside); },
    });

    app.component('v-doctor-week-calendar', {
        template: '#v-doctor-week-calendar-template',
        data() {
            const today = new Date();
            const start = new Date(today);
            const day = start.getDay();
            const diff = (day === 0 ? -6 : 1) - day;
            start.setDate(start.getDate() + diff);

            return {
                isLoading: false,
                isSaving: false,
                addError: '',
                hourHeight: 64,
                startISO: this.toISO(start),
                days: [],
                doctors: [],
                appointments: [],
                selectedDoctorIds: [],
                doctorFilterInitialized: false,
                overlayWidth: 320,
                overlayHeight: 180,
                addForm: {
                    visible: false,
                    doctorId: null,
                    doctorLabel: '',
                    day: '',
                    dayLabel: '',
                    top: 0,
                    left: 0,
                    title: '',
                    type: 'meeting',
                    startTime: '09:00',
                    endTime: '10:00',
                },
                endpoint: "{{ route('admin.activities.get') }}",
                storeUrl: "{{ route('admin.activities.store') }}",
                editUrlTemplate: "{{ route('admin.activities.edit', 'replaceId') }}",
                deleteUrlTemplate: "{{ route('admin.activities.delete', 'replaceId') }}",
            };
        },
        computed: {
            minuteHeight() { return this.hourHeight / 60; },
            dayHeight() { return 24 * this.hourHeight; },
            totalHeight() { return this.days.length * this.dayHeight; },
            weekLabel() {
                if (!this.days.length) return '';
                const s = new Date(this.days[0].date);
                const e = new Date(this.days[this.days.length - 1].date);
                return `${this.formatDate(s)} — ${this.formatDate(e)}`;
            },
            columns() {
                const ids = new Set(this.selectedDoctorIds.map(id => Number(id)));
                return this.doctors.filter(d => ids.has(Number(d.id)));
            },
        },
        mounted() {
            this.fetch();
        },
        methods: {
            pad2(n) { return String(n).padStart(2, '0'); },
            toISO(d) {
                const yyyy = d.getFullYear();
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                return `${yyyy}-${mm}-${dd}`;
            },
            formatDate(d) {
                const wd = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][d.getDay()];
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                return `${wd} ${dd}/${mm}/${yyyy}`;
            },
            formatTime(dt) {
                const t = new Date(dt);
                return `${this.pad2(t.getHours())}:${this.pad2(t.getMinutes())}`;
            },
            prevWeek() {
                const d = new Date(this.startISO);
                d.setDate(d.getDate() - 7);
                this.startISO = this.toISO(d);
                this.fetch();
            },
            nextWeek() {
                const d = new Date(this.startISO);
                d.setDate(d.getDate() + 7);
                this.startISO = this.toISO(d);
                this.fetch();
            },
            goThisWeek() {
                const today = new Date();
                const start = new Date(today);
                const day = start.getDay();
                const diff = (day === 0 ? -6 : 1) - day;
                start.setDate(start.getDate() + diff);
                this.startISO = this.toISO(start);
                this.fetch();
            },
            fetch() {
                this.isLoading = true;

                this.$axios.get(this.endpoint, { params: { view_type: 'calendar', calendar_mode: 'doctor', start: this.startISO } })
                    .then(r => {
                        this.days = r.data.days;
                        this.doctors = (r.data.doctors || []).filter(d => d && d.id);
                        this.appointments = r.data.appointments;

                        if (!this.doctorFilterInitialized) {
                            this.selectedDoctorIds = this.doctors.map(d => String(d.id));
                            this.doctorFilterInitialized = true;
                        }

                        this.isLoading = false;
                    })
                    .catch(() => this.isLoading = false);
            },
            selectAllDoctors() { this.selectedDoctorIds = this.doctors.map(d => String(d.id)); },
            clearDoctors() { this.selectedDoctorIds = []; },
            dayDoctorEvents(dateStr, doctorId) {
                return this.appointments
                    .filter(a => a.start.split(' ')[0] === dateStr && String(a.doctor_id) === String(doctorId))
                    .map(a => {
                        const dtStart = new Date(a.start);
                        const dtEnd = new Date(a.end);
                        const startMin = dtStart.getHours() * 60 + dtStart.getMinutes();
                        const endMin = dtEnd.getHours() * 60 + dtEnd.getMinutes();
                        const top = startMin * this.minuteHeight;
                        const height = Math.max((endMin - startMin) * this.minuteHeight, 8);
                        return { ...a, top, height };
                    });
            },
            totalCount(doctorId) {
                return this.appointments.filter(a => String(a.doctor_id) === String(doctorId)).length;
            },
            onColumnClick(e, doctorId) {
                const container = e.currentTarget;
                const rect = container.getBoundingClientRect();
                const yLocal = e.clientY - rect.top;
                const dayIndex = Math.floor(yLocal / this.dayHeight);
                const day = this.days[dayIndex];
                if (!day) return;

                const within = yLocal - (dayIndex * this.dayHeight);
                const minutes = Math.max(0, Math.min(23 * 60 + 59, Math.round(within / this.minuteHeight)));
                const h = Math.floor(minutes / 60);
                const m = minutes % 60;

                this.addForm.visible = true;

                const xView = e.clientX;
                const yView = e.clientY;
                const desiredLeft = xView + 8;
                const maxLeft = window.innerWidth - this.overlayWidth - 8;
                const left = desiredLeft <= maxLeft ? desiredLeft : Math.max(8, xView - this.overlayWidth - 8);

                const desiredTop = yView - this.overlayHeight - 8;
                const minTop = 8;
                const maxTop = window.innerHeight - this.overlayHeight - 8;
                const top = desiredTop >= minTop ? desiredTop : Math.min(maxTop, yView + 8);

                this.addForm.left = left;
                this.addForm.top = top;
                this.addForm.day = day.date;
                this.addForm.dayLabel = day.label;
                this.addForm.doctorId = doctorId;
                this.addForm.doctorLabel = (this.doctors.find(d => String(d.id) === String(doctorId))?.name || '');
                this.addForm.startTime = `${this.pad2(h)}:${this.pad2(m)}`;
                const endMins = Math.min(23 * 60 + 59, minutes + 60);
                const eh = Math.floor(endMins / 60);
                const em = endMins % 60;
                this.addForm.endTime = `${this.pad2(eh)}:${this.pad2(em)}`;
                this.addForm.title = '';
                this.addForm.type = 'meeting';
                this.addError = '';
            },
            cancelAdd() {
                this.addForm.visible = false;
                this.addError = '';
            },
            saveAdd() {
                this.isSaving = true;
                const start = `${this.addForm.day} ${this.addForm.startTime}`;
                const end = `${this.addForm.day} ${this.addForm.endTime}`;
                const payload = { type: this.addForm.type, title: this.addForm.title, schedule_from: start, schedule_to: end };
                if (this.addForm.doctorId) payload['participants'] = { doctors: [this.addForm.doctorId] };

                this.$axios.post(this.storeUrl, payload)
                    .then(() => {
                        this.isSaving = false;
                        this.addForm.visible = false;
                        this.fetch();
                    })
                    .catch(err => {
                        this.isSaving = false;
                        this.addError = err?.response?.data?.message || 'Error al guardar';
                    });
            },
            editUrl(id) { return this.editUrlTemplate.replace('replaceId', id); },
            remove(ev) {
                this.$axios.delete(this.deleteUrlTemplate.replace('replaceId', ev.id))
                    .then(() => this.fetch())
                    .catch(() => {});
            },
        },
    });
</script>

<style>
    .ms-container{display:inline-block;min-width:240px;position:relative}
    .ms-input{display:flex;align-items:center;justify-content:space-between;border:1px solid var(--border-color,#e5e7eb);border-radius:8px;padding:6px;gap:6px;background:var(--hours-bg,#fff)}
    .dark .ms-input{border-color:#1f2937;background:#0b0f19}
    .ms-chips{display:flex;flex-wrap:wrap;gap:6px;max-height:64px;overflow:auto}
    .ms-chip{display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border-radius:12px;padding:2px 8px;font-size:12px}
    .dark .ms-chip{background:#262b36}
    .ms-chip-x{cursor:pointer}
    .ms-actions{display:flex;align-items:center;gap:8px}
    .ms-count{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:6px;background:#f3f4f6}
    .dark .ms-count{background:#262b36}
    .ms-dropdown{position:absolute;left:0;right:0;top:calc(100% + 4px);border:1px solid var(--border-color,#e5e7eb);background:var(--hours-bg,#fff);border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.08);padding:8px;z-index:20}
    .dark .ms-dropdown{border-color:#1f2937;background:#0b0f19}
    .ms-search{width:100%;border:1px solid var(--border-color,#e5e7eb);border-radius:6px;padding:6px 8px;font-size:12px;margin-bottom:8px}
    .dark .ms-search{border-color:#1f2937;background:#0b0f19;color:#cbd5e1}
    .ms-list{max-height:180px;overflow:auto}
    .ms-item{display:flex;align-items:center;gap:8px;padding:6px;border-radius:6px;cursor:pointer}
    .ms-item:hover{background:#f3f4f6}
    .dark .ms-item:hover{background:#262b36}
    .ms-empty{padding:6px;color:#6b7280;font-size:12px}

    .dwc-container{display:flex;flex-direction:column;gap:8px;width:100%}
    .dwc-controls{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px;border:1px solid var(--border-color);border-radius:8px;background:var(--controls-bg);flex-wrap:wrap}
    .dwc-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .dwc-grid{display:grid;gap:0;border:1px solid var(--border-color);border-radius:8px;overflow:auto;background:var(--events-bg)}
    .dwc-hours{border-right:1px solid var(--border-color);background:var(--hours-bg)}
    .dwc-hour-row{display:flex;align-items:center;justify-content:flex-end;padding-right:8px;height:var(--hour-height);font-size:12px;color:var(--hour-text)}
    .dwc-doctor-col{border-right:1px solid var(--border-color);min-width:220px}
    .dwc-doctor-header{display:flex;align-items:center;justify-content:space-between;padding:8px;font-size:12px;color:var(--day-text);border-bottom:1px solid var(--border-color);background:var(--hours-bg)}
    .dwc-days-stack{position:relative}
    .dwc-day-block{position:relative;border-bottom:1px solid var(--border-color)}
    .dwc-hour-line{position:absolute;left:0;right:0;height:1px;background:var(--grid-line)}
    .dwc-event{position:absolute;left:6px;right:6px;border-left:4px solid var(--event-accent);background:var(--event-bg);color:var(--event-text);border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:6px 8px;font-size:12px}
    .dwc-event-title{font-weight:600}
    .dwc-event-time{font-size:11px;opacity:.85}
    .dwc-add-overlay{position:fixed;background:var(--hours-bg);border:1px solid var(--border-color);border-radius:6px;padding:8px;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.18)}

    :root{--hour-height:64px;--border-color:#e5e7eb;--grid-line:#eef2f7;--hours-bg:#fff;--events-bg:#fff;--hour-text:#6b7280;--day-text:#111827;--event-bg:#f8fafc;--event-text:#111827;--event-accent:#3b82f6;--controls-bg:#fff}
    .dark .dwc-grid,.dark .dwc-controls{--border-color:#1f2937;--grid-line:#101828;--hours-bg:#0b0f19;--events-bg:#0b0f19;--hour-text:#cbd5e1;--day-text:#f3f4f6;--event-bg:#111827;--event-text:#f3f4f6;--event-accent:#60a5fa;--controls-bg:#0b0f19}
    @media (max-width:640px){:root{--hour-height:48px}}
</style>
