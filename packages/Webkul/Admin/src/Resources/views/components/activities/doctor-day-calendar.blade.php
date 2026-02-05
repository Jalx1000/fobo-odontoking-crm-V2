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
                    <span>@{{ d && d.name ? d.name : '' }}</span>
                </li>
                <li v-if="!filtered.length" class="ms-empty">Sin resultados</li>
            </ul>
        </div>
    </div>
</script>

<script type="text/x-template" id="v-doctor-day-calendar-template">
    <div class="dwc-container">
        <div class="dwc-controls">
            <div class="flex items-center gap-2">
                <button class="px-2 py-1 rounded border dark:border-gray-800" @click="prevPeriod">←</button>
                <button class="px-2 py-1 rounded border dark:border-gray-800" @click="goToday">Hoy</button>
                <button
                    type="button"
                    class="px-3 py-1 rounded border dark:border-gray-800 text-sm font-semibold dark:text-white"
                    @click="toggleDatePicker"
                >
                    @{{ dayLabel }}
                </button>
                <button class="px-2 py-1 rounded border dark:border-gray-800" @click="nextPeriod">→</button>
            </div>

            <div class="dwc-filters">
                <span class="text-xs dark:text-gray-300">Filtrar doctores:</span>
                <button type="button" class="px-2 py-1 rounded border text-xs dark:border-gray-800" @click="selectAllDoctors">Todos</button>
                <button type="button" class="px-2 py-1 rounded border text-xs dark:border-gray-800" @click="clearDoctors">Ninguno</button>
                <v-doctor-multiselect style="max-width:350px;min-width:350px;max-height:100%" :items="doctors" v-model="selectedDoctorIds"></v-doctor-multiselect>
                
                <div class="flex items-center rounded border dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-900">
                    <button type="button" class="px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800" :class="viewType==='day'?'bg-gray-100 dark:bg-gray-800 font-semibold text-blue-600':''" @click="setView('day')">Día</button>
                    <button type="button" class="px-3 py-2 text-sm border-l dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800" :class="viewType==='week'?'bg-gray-100 dark:bg-gray-800 font-semibold text-blue-600':''" @click="setView('week')">Semana</button>
                    <button type="button" class="px-3 py-2 text-sm border-l dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800" :class="viewType==='month'?'bg-gray-100 dark:bg-gray-800 font-semibold text-blue-600':''" @click="setView('month')">Mes</button>
                </div>
            </div>
        </div>

        <div v-if="quickMenu.visible" class="ddc-quick-overlay" @click="closeQuickMenu"></div>

        <div
            v-if="quickMenu.visible"
            class="ddc-quick-menu"
            :style="{ top: quickMenu.top + 'px', left: quickMenu.left + 'px', width: quickMenu.width + 'px' }"
            @click.stop
        >
            <div class="ddc-quick-menu-head">
                <div class="ddc-quick-menu-time">@{{ quickMenu.timeText }}</div>
                <button type="button" class="ddc-quick-menu-close" @click="closeQuickMenu">×</button>
            </div>
            <div class="ddc-quick-menu-list">
                <button type="button" class="ddc-quick-menu-item" @click="openAppointmentModal">
                    <span class="icon-calendar text-xl"></span>
                    <span>Añadir cita</span>
                </button>
                <button type="button" class="ddc-quick-menu-item" @click="openGroupModal">
                    <span class="icon-calendar text-xl"></span>
                    <span>Añadir cita de grupo</span>
                </button>
                <button type="button" class="ddc-quick-menu-item" @click="openUnavailableModal">
                    <span class="icon-calendar text-xl"></span>
                    <span>Añadir horario no disponible</span>
                </button>
                <a class="ddc-quick-menu-link" href="#">Ajustes de acciones rápidas</a>
            </div>
        </div>

        <div v-if="datePickerOpen" class="ddc-date-overlay" @click="closeDatePicker">
            <div class="ddc-date-panel" @click.stop>
                <div class="ddc-date-panel-header">
                    <button type="button" class="ddc-date-nav" @click="shiftPicker(-1)">‹</button>
                    <div class="ddc-date-months-header">
                        <div class="ddc-date-month-title">@{{ monthTitle(month1Date) }}</div>
                        <div class="ddc-date-month-title">@{{ monthTitle(month2Date) }}</div>
                    </div>
                    <button type="button" class="ddc-date-nav" @click="shiftPicker(1)">›</button>
                </div>

                <div class="ddc-date-months">
                    <div class="ddc-date-month">
                        <div class="ddc-date-weekdays">
                            <span v-for="w in weekdays" :key="'w1-'+w" class="ddc-date-weekday">@{{ w }}</span>
                        </div>
                        <div class="ddc-date-grid">
                            <button
                                v-for="cell in month1Cells"
                                :key="'m1-'+cell.key"
                                type="button"
                                class="ddc-date-day"
                                :class="{
                                    'is-out': !cell.inMonth,
                                    'is-selected': cell.iso === startISO,
                                    'is-today': cell.iso === todayISO()
                                }"
                                :disabled="!cell.inMonth"
                                @click="selectDate(cell.iso)"
                            >@{{ cell.day }}</button>
                        </div>
                    </div>

                    <div class="ddc-date-month">
                        <div class="ddc-date-weekdays">
                            <span v-for="w in weekdays" :key="'w2-'+w" class="ddc-date-weekday">@{{ w }}</span>
                        </div>
                        <div class="ddc-date-grid">
                            <button
                                v-for="cell in month2Cells"
                                :key="'m2-'+cell.key"
                                type="button"
                                class="ddc-date-day"
                                :class="{
                                    'is-out': !cell.inMonth,
                                    'is-selected': cell.iso === startISO,
                                    'is-today': cell.iso === todayISO()
                                }"
                                :disabled="!cell.inMonth"
                                @click="selectDate(cell.iso)"
                            >@{{ cell.day }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="viewType === 'month'" class="dwc-month-view">
            <div class="ddc-date-weekdays mb-0">
                <span v-for="w in weekdays" :key="'mw-'+w" class="ddc-date-weekday">@{{ w }}</span>
            </div>
            <div class="ddc-date-grid">
                <div v-for="cell in monthGridCells" :key="'mc-'+cell.key" class="ddc-date-day dwc-month-cell" 
                    :class="{ 'is-out': !cell.inMonth, 'is-today': cell.iso === todayISO() }"
                >
                    <div class="dwc-month-cell-header">
                        <span :class="{'font-bold text-blue-600': cell.iso === todayISO()}">@{{ cell.day }}</span>
                    </div>
                    <div class="dwc-month-cell-events custom-scrollbar">
                         <div v-for="ev in getEventsForDay(cell.iso)" :key="'mev-'+ev.id" 
                              class="dwc-month-event"
                              :title="ev.title || ev.type"
                              @click="editUrl(ev.id) ? window.location.href=editUrl(ev.id) : null"
                         >
                             <span class="font-semibold">@{{ formatTime(ev.start) }}</span> @{{ ev.title || ev.type }}
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="viewType === 'week'" class="dwc-week-view">
            <div class="dwc-week-header">
                <div class="dwc-week-header-cell doctor-col">Doctores</div>
                <div v-for="day in days" :key="'wh-'+day.date" class="dwc-week-header-cell">
                    @{{ day.label }}
                </div>
            </div>
            <div class="dwc-week-body">
                <div v-for="col in columns" :key="'wrow-'+col.id" class="dwc-week-row">
                    <div class="dwc-week-doctor-cell">
                        <span class="font-semibold text-sm">@{{ col.name }}</span>
                        <span class="text-xs text-gray-500">@{{ totalCount(col.id) }} citas</span>
                    </div>
                    <div v-for="day in days" :key="'wcell-'+col.id+'-'+day.date" 
                         class="dwc-week-day-cell"
                         :class="{ 'is-today': day.date === todayISO() }"
                         @click="onWeekCellClick($event, day, col.id)"
                    >
                        <div v-for="ev in dayDoctorEvents(day.date, col.id)" :key="'wev-'+ev.id"
                             class="dwc-week-event"
                             :title="ev.title || ev.type"
                             @click.stop="editUrl(ev.id) ? window.location.href=editUrl(ev.id) : null"
                        >
                            <span class="font-bold">@{{ formatTime(ev.start) }}</span>
                            <span class="truncate">@{{ ev.title || ev.type }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="dwc-grid" :style="{ gridTemplateColumns: gridCols }">
            <div
                v-if="showNowLine"
                class="dwc-now-line"
                :style="{ top: nowTop + 'px' }"
            >
                <div class="dwc-now-time">@{{ nowText }}</div>
            </div>

            <div class="dwc-hours">
                <div v-for="h in 24" :key="'hr-'+h" class="dwc-hour-row" :style="{ height: hourHeight + 'px' }">@{{ pad2(h-1) }}:00</div>
            </div>

            <div v-for="col in columns" :key="'col-'+col.id" class="dwc-doctor-col">
                <div class="dwc-doctor-header">
                    <span>@{{ col && col.name ? col.name : '' }}</span>
                    <span class="text-xs dark:text-gray-300">@{{ totalCount(col.id) }}</span>
                </div>

                <div class="dwc-days-stack" :style="{ height: totalHeight + 'px' }" @click="onColumnClick($event, col.id)">
                    <div v-for="(day, di) in days" :key="'day-'+di" class="dwc-day-block" :style="{ height: dayHeight + 'px' }">
                        <div v-for="av in getDoctorAvailability(day.date, col.id)" :key="'av-'+av.id" 
                             class="dwc-availability-block" 
                             :style="{ top: av.top + 'px', height: av.height + 'px' }">
                        </div>

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

        <x-admin::modal ref="appointmentModal">
            <x-slot:header>
                <div class="text-lg font-semibold dark:text-white">
                    Añadir cita
                </div>
            </x-slot>

            <x-slot:content>
                <div class="flex flex-col gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        @{{ modalContext.dayLabel }} · @{{ modalContext.timeText }}
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <x-admin::lookup
                                ::src="personSearchUrl"
                                ::params="{}"
                                name="appointment_person"
                                placeholder="Paciente"
                                :can-add-new="true"
                                @on-selected="onSelectPatient"
                                ::value="{ id: appointmentForm.person.id, name: appointmentForm.person.name }"
                            />
                        </div>

                        <select
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="appointmentForm.doctor_id"
                        >
                            <option :value="null" disabled>Doctor</option>
                            <option v-for="d in doctors" :key="'doc-opt-'+d.id" :value="d.id">@{{ d && d.name ? d.name : '' }}</option>
                        </select>

                        <x-admin::lookup
                            class="col-span-1"
                            ::src="productSearchUrl"
                            ::params="{}"
                            name="appointment_product"
                            placeholder="Servicio"
                            @on-selected="onSelectService"
                            ::value="{ id: appointmentForm.product_id, name: appointmentForm.product_name }"
                        />

                        <select
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model.number="appointmentForm.duration"
                            @change="syncAppointmentEndTime"
                        >
                            <option :value="15">15 min</option>
                            <option :value="30">30 min</option>
                            <option :value="45">45 min</option>
                            <option :value="60">60 min</option>
                            <option :value="90">90 min</option>
                            <option :value="120">120 min</option>
                        </select>

                        <input
                            type="time"
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="appointmentForm.startTime"
                            @change="syncAppointmentEndTime"
                        />

                        <input
                            type="time"
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="appointmentForm.endTime"
                            @change="syncAppointmentDurationFromTimes"
                        />

                        <textarea
                            rows="3"
                            class="col-span-2 rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="appointmentForm.reason"
                            placeholder="Motivo de consulta"
                        ></textarea>
                    </div>

                    <div class="text-sm text-red-600" v-if="modalError">@{{ modalError }}</div>
                </div>
            </x-slot>

            <x-slot:footer>
                <div class="flex items-center gap-2 justify-end">
                    <button type="button" class="secondary-button" @click="$refs.appointmentModal.close()">Cancelar</button>
                    <button type="button" class="primary-button" @click="saveAppointment" :disabled="modalSaving">
                        <span v-if="!modalSaving">Guardar</span>
                        <span v-else class="flex items-center gap-2"><x-admin::spinner /> Guardando...</span>
                    </button>
                </div>
            </x-slot>
        </x-admin::modal>

        <x-admin::modal ref="groupModal">
            <x-slot:header>
                <div class="text-lg font-semibold dark:text-white">
                    Añadir cita de grupo
                </div>
            </x-slot>

            <x-slot:content>
                <div class="flex flex-col gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        @{{ modalContext.dayLabel }} · @{{ modalContext.timeText }}
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <input
                            type="text"
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="groupForm.title"
                            placeholder="Título"
                        />

                        <select
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="groupForm.type"
                        >
                            <option value="meeting">Reunión</option>
                            <option value="call">Llamada</option>
                            <option value="lunch">Almuerzo</option>
                        </select>

                        <input
                            type="time"
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="groupForm.startTime"
                        />

                        <input
                            type="time"
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="groupForm.endTime"
                        />
                    </div>

                    <div class="flex flex-col gap-1">
                        <div class="text-xs text-gray-600 dark:text-gray-300">Doctores</div>
                        <v-doctor-multiselect :items="doctors" v-model="groupForm.doctorIds"></v-doctor-multiselect>
                    </div>

                    <div class="text-sm text-red-600" v-if="modalError">@{{ modalError }}</div>
                </div>
            </x-slot>

            <x-slot:footer>
                <div class="flex items-center gap-2 justify-end">
                    <button type="button" class="secondary-button" @click="$refs.groupModal.close()">Cancelar</button>
                    <button type="button" class="primary-button" @click="saveGroupAppointment" :disabled="modalSaving">
                        <span v-if="!modalSaving">Guardar</span>
                        <span v-else class="flex items-center gap-2"><x-admin::spinner /> Guardando...</span>
                    </button>
                </div>
            </x-slot>
        </x-admin::modal>

        <x-admin::modal ref="unavailableModal">
            <x-slot:header>
                <div class="text-lg font-semibold dark:text-white">
                    Añadir horario no disponible
                </div>
            </x-slot>

            <x-slot:content>
                <div class="flex flex-col gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        @{{ modalContext.dayLabel }} · @{{ modalContext.doctorLabel }}
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <input
                            type="time"
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="unavailableForm.startTime"
                        />

                        <input
                            type="time"
                            class="rounded border px-2 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            v-model="unavailableForm.endTime"
                        />
                    </div>

                    <div class="text-sm text-red-600" v-if="modalError">@{{ modalError }}</div>
                </div>
            </x-slot>

            <x-slot:footer>
                <div class="flex items-center gap-2 justify-end">
                    <button type="button" class="secondary-button" @click="$refs.unavailableModal.close()">Cancelar</button>
                    <button type="button" class="primary-button" @click="saveUnavailable" :disabled="modalSaving">
                        <span v-if="!modalSaving">Guardar</span>
                        <span v-else class="flex items-center gap-2"><x-admin::spinner /> Guardando...</span>
                    </button>
                </div>
            </x-slot>
        </x-admin::modal>
    </div>
</script>

<script type="module">
    app.component('v-doctor-multiselect', {
        template: '#v-doctor-multiselect-template',
        props: {
            items: {
                type: Array,
                default: () => []
            },
            modelValue: {
                type: Array,
                default: () => []
            },
        },
        emits: ['update:modelValue'],
        data() {
            return {
                open: false,
                q: ''
            };
        },
        computed: {
            model() {
                return this.modelValue;
            },
            set() {
                return new Set(this.model);
            },
            filtered() {
                const q = this.q.trim().toLowerCase();
                return q ?
                    this.items.filter(d => d && String(d.name || '').toLowerCase().includes(q)) :
                    this.items.filter(d => d);
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
        mounted() {
            window.addEventListener('click', this.onClickOutside);
        },
        beforeUnmount() {
            window.removeEventListener('click', this.onClickOutside);
        },
    });

    app.component('v-doctor-day-calendar', {
        template: '#v-doctor-day-calendar-template',
        data() {
            const today = new Date();
            const start = new Date(today);

            return {
                isLoading: false,
                isSaving: false,
                addError: '',
                hourHeight: 64,
                startISO: this.toISO(start),
                days: [],
                doctors: [],
                appointments: [],
                availability: [],
                selectedDoctorIds: [],
                doctorFilterInitialized: false,
                nowMinutes: 0,
                nowText: '',
                nowTimer: null,
                datePickerOpen: false,
                datePickerMonthISO: '',
                weekdays: ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'],
                quickMenu: {
                    visible: false,
                    top: 0,
                    left: 0,
                    width: 320,
                    height: 190,
                    doctorId: null,
                    doctorLabel: '',
                    dayISO: '',
                    dayLabel: '',
                    startTime: '09:00',
                    endTime: '10:00',
                    timeText: '09:00',
                },
                modalContext: {
                    doctorId: null,
                    doctorLabel: '',
                    dayISO: '',
                    dayLabel: '',
                    timeText: '',
                },
                appointmentForm: {
                    person: {
                        id: '',
                        name: '',
                    },
                    doctor_id: null,
                    product_id: null,
                    product_name: '',
                    duration: 60,
                    startTime: '09:00',
                    endTime: '10:00',
                    reason: '',
                },
                groupForm: {
                    title: '',
                    type: 'meeting',
                    startTime: '09:00',
                    endTime: '10:00',
                    doctorIds: [],
                },
                unavailableForm: {
                    startTime: '09:00',
                    endTime: '10:00',
                },
                modalSaving: false,
                modalError: '',
                endpoint: "{{ route('admin.activities.get') }}",
                storeUrl: "{{ route('admin.activities.store') }}",
                appointmentStoreUrl: "{{ route('admin.activities.appointments.store') }}",
                personSearchUrl: "{{ route('admin.contacts.persons.search') }}",
                productSearchUrl: "{{ route('admin.products.search') }}",
                editUrlTemplate: "{{ route('admin.activities.edit', 'replaceId') }}",
                deleteUrlTemplate: "{{ route('admin.activities.delete', 'replaceId') }}",
                scheduleStoreUrl: "{{ route('admin.schedules.store') }}",
                viewType: 'day',
            };
        },
        computed: {
            minuteHeight() {
                return this.hourHeight / 60;
            },
            dayHeight() {
                return 24 * this.hourHeight;
            },
            totalHeight() {
                return this.days.length * this.dayHeight;
            },
            dayLabel() {
                if (this.viewType === 'week' && this.days.length) {
                    const s = new Date(this.days[0].date);
                    const e = new Date(this.days[this.days.length - 1].date);
                    return `${this.formatDate(s)} — ${this.formatDate(e)}`;
                }
                if (this.viewType === 'month') {
                    const d = this.parseISODate(this.startISO);
                    const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    return `${months[d.getMonth()]} ${d.getFullYear()}`;
                }
                const d = this.parseISODate(this.startISO);
                const names = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                return `${names[d.getDay()]} ${dd}/${mm}/${yyyy}`;
            },
            columns() {
                const ids = new Set(this.selectedDoctorIds.map(id => Number(id)));
                return this.doctors.filter(d => d && d.id && ids.has(Number(d.id)));
            },
            gridCols() {
                if (this.viewType === 'month') return 'repeat(7, 1fr)';
                const count = this.columns.length;
                return count ? `80px repeat(${count}, 1fr)` : '80px';
            },
            monthGridCells() {
                if (this.viewType !== 'month') return [];
                const d = this.parseISODate(this.startISO);
                // Ensure we are working with the month of startISO
                return this.buildMonthCells(new Date(d.getFullYear(), d.getMonth(), 1));
            },
            showNowLine() {
                return this.startISO === this.todayISO();
            },
            nowTop() {
                return this.nowMinutes * this.minuteHeight;
            },
            month1Date() {
                const base = this.datePickerMonthISO ? this.parseISODate(this.datePickerMonthISO) : this
                    .parseISODate(this.startISO);
                return new Date(base.getFullYear(), base.getMonth(), 1);
            },
            month2Date() {
                return new Date(this.month1Date.getFullYear(), this.month1Date.getMonth() + 1, 1);
            },
            month1Cells() {
                return this.buildMonthCells(this.month1Date);
            },
            month2Cells() {
                return this.buildMonthCells(this.month2Date);
            },
        },
        mounted() {
            this.fetch();
            this.updateNow();
            this.nowTimer = window.setInterval(this.updateNow, 30000);
            window.addEventListener('keydown', this.onKeyDown);
        },
        beforeUnmount() {
            if (this.nowTimer) {
                clearInterval(this.nowTimer);
                this.nowTimer = null;
            }
            window.removeEventListener('keydown', this.onKeyDown);
        },
        methods: {
            pad2(n) {
                return String(n).padStart(2, '0');
            },
            parseISODate(iso) {
                if (!iso || typeof iso !== 'string') {
                    return new Date();
                }

                const parts = iso.split('-').map(Number);

                if (parts.length !== 3 || parts.some(Number.isNaN)) {
                    return new Date();
                }

                const [year, month, day] = parts;

                return new Date(year, month - 1, day);
            },
            monthTitle(d) {
                const months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
                    'septiembre', 'octubre', 'noviembre', 'diciembre'
                ];
                const name = months[d.getMonth()];
                const cap = name.charAt(0).toUpperCase() + name.slice(1);
                return `${cap} ${d.getFullYear()}`;
            },
            buildMonthCells(monthStart) {
                const y = monthStart.getFullYear();
                const m = monthStart.getMonth();
                const first = new Date(y, m, 1);
                const last = new Date(y, m + 1, 0);
                const daysInMonth = last.getDate();
                const offset = first.getDay();
                const cells = [];
                for (let i = 0; i < offset; i++) {
                    cells.push({
                        key: `o-${y}-${m}-${i}`,
                        inMonth: false,
                        day: '',
                        iso: ''
                    });
                }
                for (let day = 1; day <= daysInMonth; day++) {
                    const iso = this.toISO(new Date(y, m, day));
                    cells.push({
                        key: `d-${iso}`,
                        inMonth: true,
                        day,
                        iso
                    });
                }
                while (cells.length % 7 !== 0) {
                    const i = cells.length;
                    cells.push({
                        key: `t-${y}-${m}-${i}`,
                        inMonth: false,
                        day: '',
                        iso: ''
                    });
                }
                return cells;
            },
            todayISO() {
                return this.toISO(new Date());
            },
            updateNow() {
                const now = new Date();
                this.nowMinutes = now.getHours() * 60 + now.getMinutes();
                this.nowText = `${this.pad2(now.getHours())}:${this.pad2(now.getMinutes())}`;
            },
            timeToMinutes(time) {
                if (!time) return 0;
                const [h, m] = String(time).split(':').map(Number);
                if (Number.isNaN(h) || Number.isNaN(m)) return 0;
                return (h * 60) + m;
            },
            minutesToTime(mins) {
                const safe = Math.max(0, Math.min(24 * 60 - 1, mins));
                const h = Math.floor(safe / 60);
                const m = safe % 60;
                return `${this.pad2(h)}:${this.pad2(m)}`;
            },
            syncAppointmentEndTime() {
                const startMin = this.timeToMinutes(this.appointmentForm.startTime);
                const duration = Number(this.appointmentForm.duration) || 0;
                const endMin = startMin + duration;
                this.appointmentForm.endTime = this.minutesToTime(endMin);
            },
            syncAppointmentDurationFromTimes() {
                const startMin = this.timeToMinutes(this.appointmentForm.startTime);
                const endMin = this.timeToMinutes(this.appointmentForm.endTime);
                const diff = endMin - startMin;
                if (diff > 0) {
                    this.appointmentForm.duration = diff;
                }
            },
            onSelectPatient(result) {
                this.appointmentForm.person = {
                    id: result?.id || null,
                    name: result?.name || '',
                };
            },
            onSelectService(result) {
                this.appointmentForm.product_id = result?.id || null;
                this.appointmentForm.product_name = result?.name || '';
            },
            closeDatePicker() {
                this.datePickerOpen = false;
            },
            shiftPicker(deltaMonths) {
                const base = this.datePickerMonthISO ? this.parseISODate(this.datePickerMonthISO) : this
                    .parseISODate(this.startISO);
                const d = new Date(base.getFullYear(), base.getMonth() + deltaMonths, 1);
                this.datePickerMonthISO = this.toISO(d);
            },
            selectDate(iso) {
                if (!iso) return;
                this.startISO = iso;
                this.closeDatePicker();
                this.fetch();
            },
            onKeyDown(e) {
                if (e.key === 'Escape' && this.datePickerOpen) {
                    this.closeDatePicker();
                }
                if (e.key === 'Escape' && this.quickMenu.visible) {
                    this.closeQuickMenu();
                }
            },
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
            setView(type) {
                this.viewType = type;
                this.fetch();
            },
            prevPeriod() {
                const d = this.parseISODate(this.startISO);
                if (this.viewType === 'week') d.setDate(d.getDate() - 7);
                else if (this.viewType === 'month') d.setMonth(d.getMonth() - 1);
                else d.setDate(d.getDate() - 1);
                this.startISO = this.toISO(d);
                this.fetch();
            },
            nextPeriod() {
                const d = this.parseISODate(this.startISO);
                if (this.viewType === 'week') d.setDate(d.getDate() + 7);
                else if (this.viewType === 'month') d.setMonth(d.getMonth() + 1);
                else d.setDate(d.getDate() + 1);
                this.startISO = this.toISO(d);
                this.fetch();
            },
            goToday() {
                const today = new Date();
                this.startISO = this.toISO(today);
                this.fetch();
            },
            fetch() {
                this.isLoading = true;

                this.$axios.get(this.endpoint, {
                        params: {
                            view_type: 'calendar',
                            calendar_mode: 'doctor',
                            calendar_view: this.viewType,
                            start: this.startISO
                        }
                    })
                    .then(r => {
                        this.days = r.data.days;
                        this.doctors = (r.data.doctors || []).filter(d => d && d.id);
                        this.appointments = r.data.appointments;
                        this.availability = r.data.availability || [];

                        if (!this.doctorFilterInitialized) {
                            this.selectedDoctorIds = this.doctors.map(d => String(d.id));
                            this.doctorFilterInitialized = true;
                        }

                        this.isLoading = false;
                    })
                    .catch(() => this.isLoading = false);
            },
            selectAllDoctors() {
                this.selectedDoctorIds = this.doctors.map(d => String(d.id));
            },
            clearDoctors() {
                this.selectedDoctorIds = [];
            },
            getEventsForDay(isoDate) {
                if (!isoDate || !this.selectedDoctorIds.length) return [];
                const ids = new Set(this.selectedDoctorIds.map(id => String(id)));
                return this.appointments.filter(a => 
                    a.start.startsWith(isoDate) && 
                    ids.has(String(a.doctor_id))
                );
            },
            getDoctorAvailability(dateStr, doctorId) {
                return this.availability
                    .filter(s => s.date === dateStr && String(s.doctor_id) === String(doctorId))
                    .map(s => {
                        const startMin = this.timeToMinutes(s.start_time);
                        const endMin = this.timeToMinutes(s.end_time);
                        const top = startMin * this.minuteHeight;
                        const height = (endMin - startMin) * this.minuteHeight;
                        return { ...s, top, height };
                    });
            },
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
                        return {
                            ...a,
                            top,
                            height
                        };
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

                const endMins = Math.min(23 * 60 + 59, minutes + 60);
                const eh = Math.floor(endMins / 60);
                const em = endMins % 60;

                const xView = e.clientX;
                const yView = e.clientY;
                const desiredLeft = xView + 8;
                const maxLeft = window.innerWidth - this.quickMenu.width - 8;
                const left = desiredLeft <= maxLeft ? desiredLeft : Math.max(8, xView - this.quickMenu.width -
                    8);

                const desiredTop = yView - this.quickMenu.height - 8;
                const minTop = 8;
                const maxTop = window.innerHeight - this.quickMenu.height - 8;
                const top = desiredTop >= minTop ? desiredTop : Math.min(maxTop, yView + 8);

                this.quickMenu.visible = true;
                this.quickMenu.left = left;
                this.quickMenu.top = top;
                this.quickMenu.dayISO = day.date;
                this.quickMenu.dayLabel = day.label;
                this.quickMenu.doctorId = doctorId;
                this.quickMenu.doctorLabel = (this.doctors.find(d => String(d.id) === String(doctorId))?.name ||
                    '');
                this.quickMenu.startTime = `${this.pad2(h)}:${this.pad2(m)}`;
                this.quickMenu.endTime = `${this.pad2(eh)}:${this.pad2(em)}`;
                this.quickMenu.timeText = `${this.pad2(h)}:${this.pad2(m)}`;
            },
            onWeekCellClick(e, day, doctorId) {
                const xView = e.clientX;
                const yView = e.clientY;
                const desiredLeft = xView + 8;
                const maxLeft = window.innerWidth - this.quickMenu.width - 8;
                const left = desiredLeft <= maxLeft ? desiredLeft : Math.max(8, xView - this.quickMenu.width - 8);

                const desiredTop = yView - this.quickMenu.height - 8;
                const minTop = 8;
                const maxTop = window.innerHeight - this.quickMenu.height - 8;
                const top = desiredTop >= minTop ? desiredTop : Math.min(maxTop, yView + 8);

                this.quickMenu.visible = true;
                this.quickMenu.left = left;
                this.quickMenu.top = top;
                this.quickMenu.dayISO = day.date;
                this.quickMenu.dayLabel = day.label;
                this.quickMenu.doctorId = doctorId;
                this.quickMenu.doctorLabel = (this.doctors.find(d => String(d.id) === String(doctorId))?.name || '');
                this.quickMenu.startTime = '09:00';
                this.quickMenu.endTime = '10:00';
                this.quickMenu.timeText = '09:00';
            },
            closeQuickMenu() {
                this.quickMenu.visible = false;
            },
            toggleDatePicker() {
                if (!this.datePickerOpen) {
                    const base = this.parseISODate(this.startISO);
                    this.datePickerMonthISO = this.toISO(new Date(base.getFullYear(), base.getMonth(), 1));
                }
                this.datePickerOpen = !this.datePickerOpen;
                this.closeQuickMenu();
            },
            openAppointmentModal() {
                this.modalError = '';
                this.modalSaving = false;
                this.modalContext = {
                    doctorId: this.quickMenu.doctorId,
                    doctorLabel: this.quickMenu.doctorLabel,
                    dayISO: this.quickMenu.dayISO,
                    dayLabel: this.quickMenu.dayLabel,
                    timeText: this.quickMenu.timeText,
                };
                this.appointmentForm.doctor_id = this.quickMenu.doctorId;
                this.appointmentForm.startTime = this.quickMenu.startTime;
                this.appointmentForm.duration = this.appointmentForm.duration || 60;
                this.syncAppointmentEndTime();
                this.appointmentForm.reason = '';
                this.closeQuickMenu();
                this.$refs.appointmentModal.open();
            },
            openGroupModal() {
                this.modalError = '';
                this.modalSaving = false;
                this.modalContext = {
                    doctorId: null,
                    doctorLabel: '',
                    dayISO: this.quickMenu.dayISO,
                    dayLabel: this.quickMenu.dayLabel,
                    timeText: this.quickMenu.timeText,
                };
                this.groupForm.title = '';
                this.groupForm.type = 'meeting';
                this.groupForm.startTime = this.quickMenu.startTime;
                this.groupForm.endTime = this.quickMenu.endTime;
                this.groupForm.doctorIds = this.selectedDoctorIds.length ? [...this.selectedDoctorIds] : this
                    .doctors.map(d => String(d.id));
                this.closeQuickMenu();
                this.$refs.groupModal.open();
            },
            openUnavailableModal() {
                this.modalError = '';
                this.modalSaving = false;
                this.modalContext = {
                    doctorId: this.quickMenu.doctorId,
                    doctorLabel: this.quickMenu.doctorLabel,
                    dayISO: this.quickMenu.dayISO,
                    dayLabel: this.quickMenu.dayLabel,
                    timeText: this.quickMenu.timeText,
                };
                this.unavailableForm.startTime = this.quickMenu.startTime;
                this.unavailableForm.endTime = this.quickMenu.endTime;
                this.closeQuickMenu();
                this.$refs.unavailableModal.open();
            },
            saveAppointment() {
                this.modalSaving = true;
                this.modalError = '';

                if (!this.appointmentForm.person?.name) {
                    this.modalSaving = false;
                    this.modalError = 'Debes seleccionar un paciente.';
                    return;
                }

                if (!this.appointmentForm.doctor_id) {
                    this.modalSaving = false;
                    this.modalError = 'Debes seleccionar un doctor.';
                    return;
                }

                if (!this.appointmentForm.product_id) {
                    this.modalSaving = false;
                    this.modalError = 'Debes seleccionar un servicio.';
                    return;
                }

                const payload = {
                    person: {
                        id: this.appointmentForm.person.id || null,
                        name: this.appointmentForm.person.name || '',
                    },
                    doctor_id: this.appointmentForm.doctor_id,
                    product_id: this.appointmentForm.product_id,
                    date: this.modalContext.dayISO,
                    start_time: this.appointmentForm.startTime,
                    end_time: this.appointmentForm.endTime,
                    duration_minutes: this.appointmentForm.duration,
                    reason: this.appointmentForm.reason || '',
                };

                this.$axios.post(this.appointmentStoreUrl, payload)
                    .then((response) => {
                        this.modalSaving = false;
                        this.$refs.appointmentModal.close();
                        this.fetch();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response?.data?.message || 'Cita creada correctamente.'
                        });
                    })
                    .catch(err => {
                        this.modalSaving = false;
                        this.modalError = err?.response?.data?.message || 'Error al guardar';
                    });
            },
            saveGroupAppointment() {
                this.modalSaving = true;
                this.modalError = '';

                const start = `${this.modalContext.dayISO} ${this.groupForm.startTime}`;
                const end = `${this.modalContext.dayISO} ${this.groupForm.endTime}`;
                const doctorIds = (this.groupForm.doctorIds || []).map(id => Number(id)).filter(Boolean);

                const payload = {
                    type: this.groupForm.type,
                    title: this.groupForm.title,
                    schedule_from: start,
                    schedule_to: end,
                    participants: {
                        doctors: doctorIds
                    },
                };

                this.$axios.post(this.storeUrl, payload)
                    .then(() => {
                        this.modalSaving = false;
                        this.$refs.groupModal.close();
                        this.fetch();
                    })
                    .catch(err => {
                        this.modalSaving = false;
                        this.modalError = err?.response?.data?.message || 'Error al guardar';
                    });
            },
            saveUnavailable() {
                this.modalSaving = true;
                this.modalError = '';

                this.$axios.post(this.scheduleStoreUrl, {
                        doctor_id: this.modalContext.doctorId,
                        date: this.modalContext.dayISO,
                        start_time: this.unavailableForm.startTime,
                        end_time: this.unavailableForm.endTime,
                    })
                    .then(() => {
                        this.modalSaving = false;
                        this.$refs.unavailableModal.close();
                        this.fetch();
                    })
                    .catch(err => {
                        this.modalSaving = false;
                        this.modalError = err?.response?.data?.message || 'Error al guardar';
                    });
            },
            editUrl(id) {
                return this.editUrlTemplate.replace('replaceId', id);
            },
            remove(ev) {
                this.$axios.delete(this.deleteUrlTemplate.replace('replaceId', ev.id))
                    .then(() => this.fetch())
                    .catch(() => {});
            },
        },
    });
</script>

<style>
    .ddc-date-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.08);
        backdrop-filter: blur(2px);
        z-index: 9998;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 90px 12px
    }

    .ddc-date-panel {
        width: min(860px, 96vw);
        background: var(--hours-bg, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
        padding: 18px;
        z-index: 9999
    }

    .ddc-date-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px
    }

    .ddc-date-nav {
        width: 36px;
        height: 36px;
        border-radius: 9999px;
        border: 1px solid var(--border-color, #e5e7eb);
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent
    }

    .ddc-date-months-header {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        flex: 1;
        text-align: center
    }

    .ddc-date-month-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--day-text, #111827)
    }

    .ddc-date-months {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px
    }

    .ddc-date-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 6px;
        margin-bottom: 10px;
        color: var(--hour-text, #6b7280);
        font-size: 13px
    }

    .ddc-date-weekday {
        text-align: center;
        text-transform: lowercase
    }

    .ddc-date-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 6px
    }

    .ddc-date-day {
        height: 42px;
        border-radius: 9999px;
        border: 1px solid transparent;
        background: transparent;
        font-size: 14px;
        color: var(--day-text, #111827);
        display: flex;
        align-items: center;
        justify-content: center
    }

    .ddc-date-day.is-out {
        color: transparent
    }

    .ddc-date-day:disabled {
        cursor: default
    }

    .ddc-date-day:not(:disabled):hover {
        background: rgba(103, 80, 164, 0.10);
        border-color: rgba(103, 80, 164, 0.35)
    }

    .ddc-date-day.is-selected {
        background: rgba(103, 80, 164, 0.18);
        border-color: #6750A4;
        color: #6750A4;
        font-weight: 700
    }

    .ddc-date-day.is-today {
        outline: 2px solid rgba(239, 68, 68, 0.35)
    }

    .ddc-quick-overlay {
        position: fixed;
        inset: 0;
        z-index: 9998;
        background: transparent
    }

    .ddc-quick-menu {
        position: fixed;
        background: var(--hours-bg, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 12px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18);
        z-index: 9999;
        overflow: hidden
    }

    .ddc-quick-menu-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #d1d5db;
        padding: 10px 12px
    }

    .dark .ddc-quick-menu-head {
        background: #374151
    }

    .ddc-quick-menu-time {
        font-weight: 700;
        color: #111827
    }

    .dark .ddc-quick-menu-time {
        color: #f3f4f6
    }

    .ddc-quick-menu-close {
        width: 26px;
        height: 26px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        background: transparent;
        font-size: 18px;
        line-height: 1;
        color: inherit
    }

    .ddc-quick-menu-list {
        display: flex;
        flex-direction: column;
        padding: 8px
    }

    .ddc-quick-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 8px;
        border-radius: 10px;
        background: transparent;
        border: 0;
        text-align: left
    }

    .ddc-quick-menu-item:hover {
        background: rgba(103, 80, 164, 0.10)
    }

    .ddc-quick-menu-link {
        padding: 10px 8px;
        color: #6750A4;
        font-size: 14px
    }

    .dwc-now-line {
        position: absolute;
        left: 0;
        right: 0;
        border-top: 2px solid #ef4444;
        z-index: 40;
        pointer-events: none
    }

    .dwc-now-time {
        position: absolute;
        left: 6px;
        top: -12px;
        background: var(--hours-bg, #fff);
        border: 1px solid #ef4444;
        color: #ef4444;
        font-size: 12px;
        line-height: 1;
        padding: 2px 8px;
        border-radius: 9999px
    }

    .ms-container {
        display: inline-block;
        min-width: 240px;
        position: relative
    }

    .ms-input {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 8px;
        padding: 6px;
        gap: 6px;
        background: var(--hours-bg, #fff)
    }

    .dark .ms-input {
        border-color: #1f2937;
        background: #0b0f19
    }

    .ms-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        max-height: 64px;
        overflow: auto
    }

    .ms-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #f3f4f6;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 12px
    }

    .dark .ms-chip {
        background: #262b36
    }

    .ms-chip-x {
        cursor: pointer
    }

    .ms-actions {
        display: flex;
        align-items: center;
        gap: 8px
    }

    .ms-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        background: #f3f4f6
    }

    .dark .ms-count {
        background: #262b36
    }

    .ms-dropdown {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        border: 1px solid var(--border-color, #e5e7eb);
        background: var(--hours-bg, #fff);
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        padding: 8px;
        z-index: 20
    }

    .dark .ms-dropdown {
        border-color: #1f2937;
        background: #0b0f19
    }

    .ms-search {
        width: 100%;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 6px;
        padding: 6px 8px;
        font-size: 12px;
        margin-bottom: 8px
    }

    .dark .ms-search {
        border-color: #1f2937;
        background: #0b0f19;
        color: #cbd5e1
    }

    .ms-list {
        max-height: 180px;
        overflow: auto
    }

    .ms-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px;
        border-radius: 6px;
        cursor: pointer
    }

    .ms-item:hover {
        background: #f3f4f6
    }

    .dark .ms-item:hover {
        background: #262b36
    }

    .ms-empty {
        padding: 6px;
        color: #6b7280;
        font-size: 12px
    }

    .dwc-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%
    }

    .dwc-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--controls-bg);
        flex-wrap: wrap
    }

    .dwc-filters {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap
    }

    .dwc-grid {
        display: grid;
        gap: 0;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: auto;
        background: var(--events-bg);
        position: relative
    }

    .dwc-hours {
        border-right: 1px solid var(--border-color);
        background: var(--hours-bg)
    }

    .dwc-hour-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
        height: var(--hour-height);
        font-size: 12px;
        color: var(--hour-text)
    }

    .dwc-doctor-col {
        border-right: 1px solid var(--border-color);
        min-width: 220px
    }

    .dwc-doctor-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px;
        font-size: 12px;
        color: var(--day-text);
        border-bottom: 1px solid var(--border-color);
        background: var(--hours-bg)
    }

    .dwc-days-stack {
        position: relative;
        background: #ecececff;
    }
    .dark .dwc-days-stack {
        background: #111827;
    }
    .dwc-availability-block {
        position: absolute;
        left: 0;
        right: 0;
        background: var(--hours-bg, #fff);
        z-index: 0;
    }
    .dwc-day-block {
        position: relative;
        border-bottom: 1px solid var(--border-color)
    }
    .dwc-hour-line {
        position: absolute;
        left: 0;
        right: 0;
        height: 1px;
        background: var(--grid-line);
        z-index: 1;
        pointer-events: none;
    }
    .dwc-event {
        position: absolute;
        left: 6px;
        right: 6px;
        border-left: 4px solid var(--event-accent);
        background: var(--event-bg);
        color: var(--event-text);
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        padding: 6px 8px;
        font-size: 12px;
        z-index: 2;
    }

    .dwc-event-title {
        font-weight: 600
    }

    .dwc-event-time {
        font-size: 11px;
        opacity: .85
    }

    .dwc-add-overlay {
        position: fixed;
        background: var(--hours-bg);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 8px;
        z-index: 9999;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18)
    }

    :root {
        --hour-height: 64px;
        --border-color: #e5e7eb;
        --grid-line: #eef2f7;
        --hours-bg: #fff;
        --events-bg: #fff;
        --hour-text: #6b7280;
        --day-text: #111827;
        --event-bg: #f8fafc;
        --event-text: #111827;
        --event-accent: #3b82f6;
        --controls-bg: #fff
    }

    .dark .dwc-grid,
    .dark .dwc-controls {
        --border-color: #1f2937;
        --grid-line: #101828;
        --hours-bg: #0b0f19;
        --events-bg: #0b0f19;
        --hour-text: #cbd5e1;
        --day-text: #f3f4f6;
        --event-bg: #111827;
        --event-text: #f3f4f6;
        --event-accent: #60a5fa;
        --controls-bg: #0b0f19
    }

    @media (max-width:640px) {
        :root {
            --hour-height: 48px
        }
    }

    @media (max-width:900px) {
        .ddc-date-months {
            grid-template-columns: 1fr
        }

        .ddc-date-months-header {
            grid-template-columns: 1fr
        }

        .ddc-date-panel {
            padding: 14px
        }

        .ddc-date-month-title {
            font-size: 16px
        }
    }

    .dwc-month-view {
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: var(--hours-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 10px;
    }
    .dwc-month-cell {
        height: 120px;
        align-items: flex-start;
        justify-content: flex-start;
        flex-direction: column;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    .dwc-month-cell-header {
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 4px 8px;
    }
    .dwc-month-cell-events {
        width: 100%;
        padding: 0 4px;
        overflow-y: auto;
        max-height: 90px;
    }
    .dwc-month-event {
        font-size: 11px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 4px;
        padding: 2px 4px;
        border-radius: 4px;
        background: #dbeafe;
        color: #1e40af;
        cursor: pointer;
    }
    .dark .dwc-month-event {
        background: #1e3a8a;
        color: #dbeafe;
    }

    .dwc-week-view {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--hours-bg);
        overflow: auto;
    }
    .dwc-week-header {
        display: grid;
        grid-template-columns: 150px repeat(7, 1fr);
        border-bottom: 1px solid var(--border-color);
        background: var(--gray-50);
    }
    .dark .dwc-week-header {
        background: var(--hours-bg);
    }
    .dwc-week-header-cell {
        padding: 10px;
        text-align: center;
        font-weight: 600;
        border-right: 1px solid var(--border-color);
        font-size: 13px;
        color: var(--day-text);
    }
    .dwc-week-header-cell.doctor-col {
        text-align: left;
        padding-left: 16px;
    }
    .dwc-week-body {
        display: flex;
        flex-direction: column;
    }
    .dwc-week-row {
        display: grid;
        grid-template-columns: 150px repeat(7, 1fr);
        border-bottom: 1px solid var(--border-color);
        min-height: 80px;
    }
    .dwc-week-doctor-cell {
        padding: 10px;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: var(--gray-50);
        color: var(--day-text);
    }
    .dark .dwc-week-doctor-cell {
        background: var(--hours-bg);
    }
    .dwc-week-day-cell {
        padding: 4px;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        gap: 4px;
        cursor: pointer;
    }
    .dwc-week-day-cell:hover {
        background-color: rgba(0,0,0,0.02);
    }
    .dwc-week-day-cell.is-today {
        background-color: rgba(59, 130, 246, 0.05);
    }
    .dwc-week-event {
        font-size: 11px;
        background: #e0f2fe;
        color: #0369a1;
        padding: 2px 4px;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        gap: 4px;
        align-items: center;
        overflow: hidden;
    }
    .dark .dwc-week-event {
        background: #075985;
        color: #e0f2fe;
    }
</style>
