{{--
    Registro del componente Vue <v-smd-sync-control>.
    Sigue el patrón de doctor-day-calendar: define el template y el componente
    directamente (sin @pushOnce) para poder incluirse fuera de un text/x-template.
    El tag <v-smd-sync-control></v-smd-sync-control> se coloca donde va el control.
--}}
<script type="text/x-template" id="v-smd-sync-control-template">
    <div class="flex items-center gap-x-2">
        <!-- Toggle Pausar / Reanudar sincronización automática -->
        <button
            type="button"
            class="flex items-center gap-x-1.5 rounded-md border px-2.5 py-1.5 font-semibold transition-all"
            :class="paused
                ? 'border-gray-300 text-gray-500 hover:border-gray-400 dark:border-gray-700 dark:text-gray-400'
                : 'border-green-300 text-green-700 hover:border-green-400 dark:border-green-800 dark:text-green-400'"
            :disabled="pauseLoading"
            :title="paused
                ? '@lang('admin::app.activities.index.smd-sync.resume-hint')'
                : '@lang('admin::app.activities.index.smd-sync.pause-hint')'"
            @click="togglePause"
        >
            <span
                class="inline-block h-2 w-2 rounded-full"
                :class="paused ? 'bg-gray-400' : 'animate-pulse bg-green-500'"
            ></span>

            <span v-if="! paused">@lang('admin::app.activities.index.smd-sync.pause')</span>
            <span v-else>@lang('admin::app.activities.index.smd-sync.resume')</span>
        </button>

        <!-- Sincronizar ahora (manual, siempre disponible) -->
        <button
            type="button"
            class="secondary-button flex items-center gap-x-1.5"
            :disabled="isSyncing"
            @click="syncNow"
        >
            <span
                v-if="isSyncing"
                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            ></span>

            <span v-if="! isSyncing">@lang('admin::app.activities.index.smd-sync.sync-now')</span>
            <span v-else>@lang('admin::app.activities.index.smd-sync.syncing')</span>
        </button>
    </div>
</script>

<script type="module">
    app.component('v-smd-sync-control', {
        template: '#v-smd-sync-control-template',

        data() {
            return {
                isSyncing: false,
                pauseLoading: false,
                paused: false,

                syncUrl: "{{ route('admin.activities.smd.sync') }}",
                statusUrl: "{{ route('admin.activities.smd.sync.status') }}",
                pauseUrl: "{{ route('admin.activities.smd.pause') }}",
            };
        },

        mounted() {
            this.$axios.get(this.statusUrl)
                .then(({ data }) => {
                    this.paused = !! data.paused;

                    if (data.state === 'running') {
                        this.isSyncing = true;
                        this.pollSyncStatus();
                    } else if (! this.paused) {
                        // Auto-arranque al entrar (el backend respeta cooldown).
                        this.syncNow(true);
                    }
                })
                .catch(() => {});
        },

        methods: {
            /**
             * Dispara la sincronización. auto=true → arranque al entrar
             * (silencioso, respeta pausa/cooldown server-side).
             */
            syncNow(auto = false) {
                if (this.isSyncing) {
                    return;
                }

                this.isSyncing = true;

                this.$axios.post(this.syncUrl, { auto: auto === true })
                    .then(({ data }) => {
                        if (data.skipped) {
                            this.isSyncing = false;
                            return;
                        }

                        if (! auto) {
                            this.$emitter.emit('add-flash', { type: 'info', message: data.message });
                        }

                        this.pollSyncStatus();
                    })
                    .catch(error => {
                        const status = error.response?.status;
                        this.isSyncing = false;

                        if (status === 409) {
                            this.$emitter.emit('add-flash', { type: 'warning', message: error.response.data.message });
                            this.isSyncing = true;
                            this.pollSyncStatus();
                        } else if (! auto) {
                            this.$emitter.emit('add-flash', {
                                type: 'warning',
                                message: 'No se pudo iniciar la sincronización con ShareMeData.',
                            });
                        }
                    });
            },

            /**
             * Pausa o reanuda la sincronización automática.
             */
            togglePause() {
                this.pauseLoading = true;

                this.$axios.post(this.pauseUrl, { paused: ! this.paused })
                    .then(({ data }) => {
                        this.paused = !! data.paused;
                        this.$emitter.emit('add-flash', { type: 'success', message: data.message });
                    })
                    .catch(() => {
                        this.$emitter.emit('add-flash', {
                            type: 'warning',
                            message: 'No se pudo cambiar el estado de la sincronización.',
                        });
                    })
                    .finally(() => {
                        this.pauseLoading = false;
                    });
            },

            /**
             * Sigue el estado del sync por polling hasta que finalice.
             */
            pollSyncStatus() {
                const startedAt = Date.now();
                const MAX_WAIT = 120000;
                const INTERVAL = 4000;

                const poll = () => {
                    this.$axios.get(this.statusUrl)
                        .then(({ data }) => {
                            this.paused = !! data.paused;

                            if (data.state === 'running' && (Date.now() - startedAt) < MAX_WAIT) {
                                setTimeout(poll, INTERVAL);
                                return;
                            }

                            this.isSyncing = false;

                            if (data.state === 'done') {
                                this.$emitter.emit('add-flash', { type: 'success', message: data.message });
                                this.$emitter.emit('smd-sync-completed');
                            } else if (data.state === 'failed') {
                                this.$emitter.emit('add-flash', { type: 'error', message: data.message });
                            }
                        })
                        .catch(() => {
                            this.isSyncing = false;
                        });
                };

                poll();
            },
        },
    });
</script>
