<x-admin::layouts>
    <x-slot:title>
        Turnos programados
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex items-center gap-2">
                <div class="text-xl font-bold dark:text-white">
                    Turnos programados
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.schedules.create') }}" class="primary-button">
                    Añadir
                </a>
            </div>
        </div>

        <v-schedules>
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <x-admin::shimmer.datagrid :is-multi-row="false"/>
            </div>
        </v-schedules>

    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-schedules-template">
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <button type="button" class="secondary-button" @click="goPrevWeek">‹</button>
                        <div class="rounded border px-3 py-1 dark:border-gray-800">
                            @{{ rangeLabel }}
                        </div>
                        <button type="button" class="secondary-button" @click="goNextWeek">›</button>
                        <button type="button" class="secondary-button" @click="goThisWeek">Esta semana</button>
                    </div>
                </div>

                <div class="overflow-auto">
                    <table class="min-w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="w-64 border-b p-2 text-left dark:border-gray-800">Miembro del equipo</th>
                                <th v-for="day in days" class="border-b p-2 text-left dark:border-gray-800">
                                    <div class="flex items-center justify-between">
                                        <span>@{{ day.label }}</span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">@{{ dayTotal(day.date) }}</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="doctor in doctors" :key="doctor.id">
                                <td class="border-b p-3 align-top dark:border-gray-800">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                @{{ initials(doctor.name) }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-semibold dark:text-white">@{{ doctor.name }}</span>
                                                <span class="text-xs text-gray-600 dark:text-gray-400">@{{ doctorWeeklyTotal(doctor.id) }}</span>
                                            </div>
                                        </div>
                                        <a :href="'{{ route('admin.doctor.edit', 'replaceId') }}'.replace('replaceId', doctor.id)" class="icon-edit text-2xl"></a>
                                    </div>
                                </td>
                                <td v-for="day in days" class="border-b p-3 align-top dark:border-gray-800">
                                    <div class="flex flex-col gap-2">
                                        <template v-if="shiftsFor(doctor.id, day.date).length">
                                            <div class="rounded bg-violet-100 px-2 py-1 text-violet-800 dark:bg-violet-900 dark:text-violet-100" v-for="shift in shiftsFor(doctor.id, day.date)" :key="shift.id">
                                                <div class="flex items-center justify-between">
                                                    <span>@{{ shift.start_time }} - @{{ shift.end_time }}</span>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" class="icon-edit text-xl" @click="openEdit(shift, doctor.id, day.date)"></button>
                                                        <button type="button" class="icon-delete text-xl text-red-600" @click="deleteShift(shift)"></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="rounded bg-gray-100 px-2 py-1 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                No está trabajando
                                            </div>
                                        </template>
                                        <div class="mt-2">
                                            <button
                                                type="button"
                                                class="secondary-button"
                                                :data-doctor="doctor.id"
                                                :data-date="day.date"
                                                @click="openAdd(doctor.id, day.date)"
                                            >
                                                Añadir horario
                                            </button>
                                        </div>
                                        <div
                                            v-if="addDlg.visible && addDlg.doctorId === doctor.id && addDlg.date === day.date"
                                            class="add-overlay"
                                        >
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="text-xs dark:text-gray-300">@{{ day.label }}</span>
                                                <span class="text-xs dark:text-gray-300">·</span>
                                                <span class="text-xs dark:text-gray-300">@{{ doctor.name }}</span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="time" class="rounded border px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" v-model="addDlg.startTime" />
                                                <input type="time" class="rounded border px-2 py-1 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" v-model="addDlg.endTime" />
                                            </div>
                                            <div class="mt-2 flex items-center gap-2">
                                                <button type="button" class="secondary-button" @click="cancelAdd">Cancelar</button>
                                                <button type="button" class="primary-button" @click="saveSchedule" :disabled="addDlg.saving">
                                                    <span v-if="!addDlg.saving">Guardar</span>
                                                    <span v-else class="flex items-center gap-2"><x-admin::spinner /> Guardando...</span>
                                                </button>
                                            </div>
                                            <div class="mt-1 text-sm text-red-600" v-if="addDlg.error">@{{ addDlg.error }}</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 rounded-lg border border-dashed px-3 py-2 text-xs text-gray-600 dark:border-gray-800 dark:text-gray-400">
                    El calendario del equipo muestra tu disponibilidad para reservas y no está vinculado al horario habitual del negocio.
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-schedules', {
                template: '#v-schedules-template',
                data() {
                    return {
                        days: [],
                        range: { start: '', end: '' },
                        doctors: [],
                        shifts: [],
                        start: '',
                        addDlg: {
                            visible: false,
                            doctorId: null,
                            date: '',
                            shiftId: null,
                            startTime: '09:00',
                            endTime: '10:00',
                            saving: false,
                            error: '',
                        },
                    };
                },
                computed: {
                    rangeLabel() {
                        return `${this.formatDate(this.range.start)} - ${this.formatDate(this.range.end)}`;
                    },
                },
                mounted() {
                    const today = new Date();
                    const startOfWeek = this.toISO(this.getStartOfWeek(today));
                    this.start = startOfWeek;
                    this.fetchWeek();
                },
                methods: {
                    fetchWeek() {
                        const url = new URL('{{ route('admin.schedules.week') }}', window.location.origin);
                        url.searchParams.set('start', this.start);
                        fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.json())
                            .then(d => {
                                this.days = d.days;
                                this.range = d.range;
                                this.doctors = d.doctors;
                                this.shifts = d.shifts;
                            });
                    },
                    openAdd(doctorId, date) {
                        this.addDlg.visible = true;
                        this.addDlg.doctorId = doctorId;
                        this.addDlg.date = date;
                        this.addDlg.shiftId = null;
                        this.addDlg.error = '';
                    },
                    openEdit(shift, doctorId, date) {
                        this.addDlg.visible = true;
                        this.addDlg.doctorId = doctorId;
                        this.addDlg.date = date;
                        this.addDlg.shiftId = shift.id;
                        this.addDlg.startTime = shift.start_time;
                        this.addDlg.endTime = shift.end_time;
                        this.addDlg.error = '';
                    },
                    cancelAdd() {
                        this.addDlg.visible = false;
                        this.addDlg.error = '';
                    },
                    getCsrf() {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) return meta.getAttribute('content') || '';
                        const inp = document.querySelector('input[name="_token"]');
                        return (inp && inp.value) || '';
                    },
                    saveSchedule() {
                        this.addDlg.saving = true;
                        const payload = {
                            doctor_id: this.addDlg.doctorId,
                            date: this.addDlg.date,
                            start_time: this.addDlg.startTime,
                            end_time: this.addDlg.endTime,
                        };
                        const csrf = this.getCsrf();
                        const isEdit = !!this.addDlg.shiftId;
                        const url = isEdit
                            ? '{{ route('admin.schedules.update', 'replaceId') }}'.replace('replaceId', this.addDlg.shiftId)
                            : '{{ route('admin.schedules.store') }}';
                        const method = isEdit ? 'PUT' : 'POST';
                        fetch(url, {
                                method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrf,
                                },
                                body: JSON.stringify(payload),
                            })
                            .then(async (r) => {
                                if (!r.ok) {
                                    const err = await r.json().catch(() => ({}));
                                    throw new Error(err.message || 'Error al guardar');
                                }
                                return r.json();
                            })
                            .then((shift) => {
                                if (isEdit) {
                                    const idx = this.shifts.findIndex(s => s.id === shift.id);
                                    if (idx !== -1) this.shifts.splice(idx, 1, shift);
                                } else {
                                    this.shifts.push(shift);
                                }
                                this.addDlg.visible = false;
                            })
                            .catch((e) => {
                                this.addDlg.error = e.message || 'Error al guardar';
                            })
                            .finally(() => {
                                this.addDlg.saving = false;
                            });
                    },
                    deleteShift(shift) {
                        const csrf = this.getCsrf();
                        fetch('{{ route('admin.schedules.delete', 'replaceId') }}'.replace('replaceId', shift.id), {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrf,
                            },
                        })
                        .then(async (r) => {
                            if (!r.ok) {
                                const err = await r.json().catch(() => ({}));
                                throw new Error(err.message || 'No se pudo eliminar el turno');
                            }
                        })
                        .then(() => {
                            const idx = this.shifts.findIndex(s => s.id === shift.id);
                            if (idx !== -1) this.shifts.splice(idx, 1);
                        })
                        .catch((e) => {
                            this.addDlg.error = e.message || 'No se pudo eliminar el turno';
                        });
                    },
                    goPrevWeek() {
                        const date = new Date(this.start);
                        date.setDate(date.getDate() - 7);
                        this.start = this.toISO(date);
                        this.fetchWeek();
                    },
                    goNextWeek() {
                        const date = new Date(this.start);
                        date.setDate(date.getDate() + 7);
                        this.start = this.toISO(date);
                        this.fetchWeek();
                    },
                    goThisWeek() {
                        const today = new Date();
                        const startOfWeek = this.toISO(this.getStartOfWeek(today));
                        this.start = startOfWeek;
                        this.fetchWeek();
                    },
                    shiftsFor(doctorId, date) {
                        return this.shifts.filter(s => s.doctor_id === doctorId && s.date === date);
                    },
                    minutes(shift) {
                        const [sh, sm] = shift.start_time.split(':').map(Number);
                        const [eh, em] = shift.end_time.split(':').map(Number);
                        return (eh * 60 + em) - (sh * 60 + sm);
                    },
                    dayTotal(date) {
                        const mins = this.shifts.filter(s => s.date === date).reduce((sum, s) => sum + this.minutes(s), 0);
                        return this.fmtMinutes(mins);
                    },
                    doctorWeeklyTotal(doctorId) {
                        const mins = this.shifts.filter(s => s.doctor_id === doctorId).reduce((sum, s) => sum + this.minutes(s), 0);
                        return this.fmtMinutes(mins);
                    },
                    fmtMinutes(mins) {
                        if (mins <= 0) return '0 min';
                        const h = Math.floor(mins / 60);
                        const m = mins % 60;
                        if (m === 0) return `${h} h`;
                        return `${h} h ${m} min`;
                    },
                    formatDate(iso) {
                        const d = new Date(iso);
                        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
                    },
                    toISO(date) {
                        const tz = date.getTimezoneOffset();
                        const local = new Date(date.getTime() - tz * 60000);
                        return local.toISOString().split('T')[0];
                    },
                    getStartOfWeek(date) {
                        const d = new Date(date);
                        const day = d.getDay();
                        const diff = (day === 0 ? -6 : 1) - day;
                        d.setDate(d.getDate() + diff);
                        return d;
                    },
                    initials(name) {
                        const parts = (name || '').trim().split(/\s+/);
                        return parts.slice(0, 2).map(p => p[0]?.toUpperCase() || '').join('');
                    },
                },
            });
        </script>
    @endPushOnce
    @pushOnce('styles')
        <style>
            .add-overlay{position:relative;margin-top:8px;border:1px solid var(--border-color,#e5e7eb);border-radius:6px;padding:8px;background:var(--hours-bg,#fff)}
            .dark .add-overlay{border-color:#1f2937;background:#0b0f19}
            @media (max-width:640px){.add-overlay{margin-top:6px}}
        </style>
    @endPushOnce
</x-admin::layouts>
