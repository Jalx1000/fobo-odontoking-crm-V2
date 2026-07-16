@php
    /**
     * The inbox is shared by the lead view and the person view. Build a common
     * context array from whichever entity is in scope ($lead or $person).
     */
    $waPerson = $person ?? ($lead->person ?? null);

    $waPhone = null;
    if ($waPerson && is_array($waPerson->contact_numbers)) {
        $first   = $waPerson->contact_numbers[0] ?? null;
        $waPhone = is_array($first) ? ($first['value'] ?? null) : $first;
    }

    $waContext = [
        'entityType'   => isset($person) ? 'person' : 'lead',
        'personId'     => $waPerson->id ?? null,
        'leadId'       => $lead->id ?? null,
        'name'         => $waPerson->name ?? ($lead->title ?? 'Contacto'),
        'phone'        => $waPhone,
        'conversationId' => null, // resolved by backend in later sprints
    ];
@endphp

<div class="p-2">
    <v-whatsapp-inbox
        :context='@json($waContext)'
        :use-mock="{{ config('whatsapp.ui.use_mock') ? 'true' : 'false' }}"
        thread-url="{{ route('admin.whatsapp.thread') }}"
        send-url="{{ route('admin.whatsapp.send') }}"
        agent-url-base="{{ url(config('app.admin_path').'/whatsapp/conversations') }}"
    ></v-whatsapp-inbox>
</div>

@pushOnce('scripts')
    <style>
        .wa-scroll::-webkit-scrollbar { width: 6px; }
        .wa-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,.18); border-radius: 3px; }
        .dark .wa-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.18); }
    </style>

    <script type="text/x-template" id="v-whatsapp-inbox-template">
        <div class="flex flex-col rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-sm font-semibold text-white">
                        @{{ initials }}
                    </div>
                    <div class="flex flex-col">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">@{{ context.name }}</div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span v-if="context.phone">@{{ context.phone }}</span>
                            <span v-else class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Sin número</span>

                            <!-- 24h WhatsApp window -->
                            <span v-if="window.applies && window.open"
                                  class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                  title="Tiempo restante para enviar texto libre">
                                ⏱ @{{ windowLabel }}
                            </span>
                            <span v-else-if="window.applies && !window.open"
                                  class="rounded-full bg-red-100 px-1.5 py-0.5 text-[11px] font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                  title="Fuera de la ventana de 24h">
                                Ventana cerrada
                            </span>
                        </div>
                    </div>
                </div>

                <!-- AI agent switch -->
                <label class="flex cursor-pointer items-center gap-2 text-xs text-gray-600 dark:text-gray-300"
                       :class="agentToggling ? 'opacity-50 pointer-events-none' : ''">
                    <span>Agente IA</span>
                    <span class="relative inline-flex h-5 w-9 items-center">
                        <input type="checkbox" :checked="aiEnabled" @change="toggleAgent($event.target.checked)" class="peer sr-only">
                        <span class="h-5 w-9 rounded-full bg-gray-300 transition peer-checked:bg-emerald-500 dark:bg-gray-600"></span>
                        <span class="absolute left-0.5 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-4"></span>
                    </span>
                </label>
            </div>

            <!-- Messages -->
            <div ref="scroll" class="wa-scroll flex h-[440px] flex-col gap-1.5 overflow-y-auto bg-[#efeae2] px-4 py-3 dark:bg-[#0b141a]">
                <div v-if="isLoading" class="m-auto flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <x-admin::spinner /> <span>Cargando conversación…</span>
                </div>

                <div v-else-if="!messages.length" class="m-auto text-sm text-gray-500 dark:text-gray-400">
                    Sin mensajes todavía.
                </div>

                <template v-else v-for="(msg, i) in messages" :key="msg.id">
                    <!-- day separator: Hoy / Ayer / 15 jul 2026 -->
                    <div v-if="i === 0 || messages[i - 1].date !== msg.date" class="my-1 flex justify-center">
                        <span class="rounded-full bg-black/10 px-3 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                            @{{ dayLabel(msg.date) }}
                        </span>
                    </div>

                    <div class="flex" :class="msg.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                        <div
                            class="max-w-[78%] rounded-lg px-2.5 py-1.5 text-sm shadow-sm"
                            :class="msg.direction === 'outbound'
                                ? 'bg-[#d9fdd3] text-gray-800 dark:bg-emerald-800 dark:text-gray-50'
                                : 'bg-white text-gray-800 dark:bg-gray-700 dark:text-gray-50'"
                        >
                            <!-- reply quote -->
                            <div v-if="msg.replyTo" class="mb-1 border-l-2 border-emerald-500 bg-black/5 px-2 py-1 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                <div class="font-semibold">@{{ msg.replyTo.author }}</div>
                                <div class="truncate">@{{ msg.replyTo.preview }}</div>
                            </div>

                            <!-- author label -->
                            <div v-if="msg.direction === 'outbound'" class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                 :class="msg.sender === 'ia' ? 'text-emerald-600 dark:text-emerald-300' : 'text-sky-600 dark:text-sky-300'">
                                @{{ msg.sender === 'ia' ? 'IA' : 'Agente' }}
                            </div>

                            <!-- body by type -->
                            <div v-if="msg.type === 'text'" class="whitespace-pre-wrap break-words" v-html="formatText(msg.text)"></div>

                            <div v-else-if="msg.type === 'image'">
                                <img :src="msg.media.url" class="max-h-56 rounded-md" alt="imagen">
                                <div v-if="msg.text" class="mt-1 whitespace-pre-wrap break-words">@{{ msg.text }}</div>
                            </div>

                            <div v-else-if="msg.type === 'sticker'">
                                <img :src="msg.media.url" class="h-28 w-28 object-contain" alt="sticker">
                            </div>

                            <div v-else-if="msg.type === 'audio'" class="min-w-[220px]">
                                <audio controls :src="msg.media.url" class="w-full"></audio>
                            </div>

                            <div v-else-if="msg.type === 'video'">
                                <video controls :src="msg.media.url" class="max-h-56 rounded-md"></video>
                            </div>

                            <a v-else-if="msg.type === 'document'" :href="msg.media.url" target="_blank"
                               class="flex items-center gap-2 rounded-md bg-black/5 px-2 py-2 dark:bg-white/10">
                                <span class="icon-file text-xl"></span>
                                <span class="flex flex-col">
                                    <span class="font-medium">@{{ msg.media.filename || 'Documento' }}</span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-300">@{{ msg.media.meta }}</span>
                                </span>
                            </a>

                            <div v-else-if="msg.type === 'location'" class="min-w-[200px]">
                                <div class="flex items-center gap-2 rounded-md bg-black/5 px-2 py-2 dark:bg-white/10">
                                    <span class="icon-map text-xl"></span>
                                    <span class="flex flex-col">
                                        <span class="font-medium">Ubicación</span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-300">@{{ msg.location.lat }}, @{{ msg.location.lng }}</span>
                                    </span>
                                </div>
                            </div>

                            <div v-else-if="msg.type === 'contact'" class="min-w-[200px]">
                                <div class="flex items-center gap-2 rounded-md bg-black/5 px-2 py-2 dark:bg-white/10">
                                    <span class="icon-user text-xl"></span>
                                    <span class="flex flex-col">
                                        <span class="font-medium">@{{ msg.contact.name }}</span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-300">@{{ msg.contact.phone }}</span>
                                    </span>
                                </div>
                            </div>

                            <div v-else class="italic text-gray-500">Tipo no soportado</div>

                            <!-- why it failed: the agent is who needs this, not the log -->
                            <div v-if="msg.status === 'failed' && msg.error"
                                 class="mt-1 rounded border border-red-300 bg-red-50 px-2 py-1 text-[11px] text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
                                <span class="font-semibold">No se envió:</span> @{{ msg.error }}
                            </div>

                            <!-- meta row -->
                            <div class="mt-0.5 flex items-center justify-end gap-1 text-[10px] text-gray-500 dark:text-gray-300">
                                <span>@{{ msg.time }}</span>
                                <span v-if="msg.direction === 'outbound'"
                                      :class="msg.status === 'read' ? 'text-sky-500' : (msg.status === 'failed' ? 'text-red-500' : '')">
                                    @{{ statusTick(msg.status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Composer -->
            <div class="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                <!-- reply preview -->
                <div v-if="replyingTo" class="mb-2 flex items-center justify-between rounded-md border-l-2 border-emerald-500 bg-black/5 px-2 py-1 text-xs dark:bg-white/10">
                    <div class="min-w-0">
                        <div class="font-semibold text-emerald-600 dark:text-emerald-300">Respondiendo a @{{ replyingTo.author }}</div>
                        <div class="truncate text-gray-600 dark:text-gray-300">@{{ replyingTo.preview }}</div>
                    </div>
                    <button class="ml-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200" @click="replyingTo = null">✕</button>
                </div>

                <div v-if="!context.phone" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                    Este contacto no tiene número de WhatsApp. Asigna un teléfono para poder chatear.
                </div>

                <div v-else-if="aiEnabled" class="flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                    <span class="icon-robot text-base"></span>
                    <span>El agente IA está atendiendo esta conversación. <button class="font-semibold underline" @click="toggleAgent(false)">Desactivalo</button> para escribir a mano.</span>
                </div>

                <div v-else-if="window.applies && !window.open" class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-900/20 dark:text-red-300">
                    Ventana de 24h cerrada. WhatsApp no permite texto libre hasta que el cliente vuelva a escribir (o usar una plantilla aprobada).
                </div>

                <template v-else>
                    <!-- reminder to re-enable the agent when the human is done -->
                    <div v-if="agentNote" class="mb-2 flex items-center justify-between rounded-md bg-amber-50 px-3 py-1.5 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        <span>Desactivaste el agente. Acordate de <button class="font-semibold underline" @click="toggleAgent(true)">reactivarlo</button> cuando termines.</span>
                        <button class="ml-2 text-amber-600 hover:text-amber-800" @click="agentNote = false">✕</button>
                    </div>

                <div class="flex items-end gap-2">
                    <!-- Only rendered when the active provider can actually send attachments -->
                    <div v-if="canAttach" class="relative">
                        <button type="button" @click="showAttach = !showAttach"
                                class="flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700">
                            <span class="icon-attachment text-xl"></span>
                        </button>
                        <div v-if="showAttach" class="absolute bottom-11 left-0 z-10 w-40 rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            <button v-if="canSend('image')" class="flex w-full items-center gap-2 px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700" @click="mockAttach('image')">
                                <span class="icon-image"></span> Imagen
                            </button>
                            <button v-if="canSend('document')" class="flex w-full items-center gap-2 px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700" @click="mockAttach('document')">
                                <span class="icon-file"></span> Documento
                            </button>
                            <button v-if="canSend('audio')" class="flex w-full items-center gap-2 px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700" @click="mockAttach('audio')">
                                <span class="icon-microphone"></span> Audio
                            </button>
                        </div>
                    </div>

                    <textarea
                        v-model="draft"
                        rows="1"
                        placeholder="Escribe un mensaje…"
                        @keydown.enter.exact.prevent="send"
                        class="max-h-28 flex-1 resize-none rounded-2xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    ></textarea>

                    <button type="button" @click="send" :disabled="!draft.trim()"
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500 text-white transition hover:bg-emerald-600 disabled:opacity-40">
                        <span class="icon-send text-xl"></span>
                    </button>
                </div>
                </template>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-whatsapp-inbox', {
            template: '#v-whatsapp-inbox-template',

            props: {
                context: { type: Object, required: true },
                useMock: { type: Boolean, default: true },
                threadUrl: { type: String, default: '' },
                sendUrl: { type: String, default: '' },
                agentUrlBase: { type: String, default: '' },
            },

            data() {
                return {
                    messages: [],
                    draft: '',
                    isLoading: false,
                    aiEnabled: false,
                    replyingTo: null,
                    showAttach: false,
                    since: null,
                    pollTimer: null,
                    // What the active provider can do. Conservative default:
                    // text only, until the server says otherwise.
                    capabilities: { send: ['text'], receive: [] },
                    // WhatsApp 24h window; refreshed by every poll.
                    window: { applies: false, open: true, seconds_left: null },
                    conversationId: null,
                    agentToggling: false,
                    agentNote: false,
                };
            },

            computed: {
                initials() {
                    return (this.context.name || '?')
                        .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
                },

                canAttach() {
                    return ['image', 'document', 'audio'].some(type => this.canSend(type));
                },

                windowLabel() {
                    let s = this.window.seconds_left;
                    if (s == null) return '';
                    const h = Math.floor(s / 3600);
                    const m = Math.floor((s % 3600) / 60);
                    return h > 0 ? `${h}h ${m}m` : `${m}m`;
                },
            },

            mounted() {
                if (this.useMock) {
                    this.loadMock();
                } else {
                    this.startReal();
                }
            },

            beforeUnmount() {
                if (this.pollTimer) clearInterval(this.pollTimer);
            },

            methods: {
                canSend(type) {
                    return (this.capabilities?.send ?? []).includes(type);
                },

                toggleAgent(enabled) {
                    // Mock mode has no backend; just flip the flag.
                    if (this.useMock) {
                        this.aiEnabled = enabled;
                        this.agentNote = !enabled;
                        return;
                    }
                    if (!this.conversationId || this.agentToggling) return;

                    this.agentToggling = true;
                    this.$axios.patch(`${this.agentUrlBase}/${this.conversationId}/agent`, { enabled })
                        .then(res => {
                            this.aiEnabled = res.data?.agent?.effective ?? enabled;
                            // Show the reminder only when the human just took over.
                            this.agentNote = !this.aiEnabled;
                            if (this.aiEnabled) this.showAttach = false;
                        })
                        .catch(() => {})
                        .finally(() => { this.agentToggling = false; });
                },

                loadMock() {
                    const t = (h, m) => `${h}:${m}`;
                    this.messages = [
                        { id: 1, direction: 'inbound', type: 'text', text: 'Hola, buenas tardes 👋', time: t('14', '02'), status: 'read' },
                        { id: 2, direction: 'inbound', type: 'text', text: 'Quiero cotizar unas tarjetas de presentación', time: t('14', '02'), status: 'read' },
                        { id: 3, direction: 'outbound', sender: 'ia', type: 'text', text: '¡Hola! Claro que sí. ¿Cuántas unidades necesitas?', time: t('14', '03'), status: 'read' },
                        { id: 4, direction: 'inbound', type: 'image', text: 'Este es el diseño que tengo', media: { url: 'https://placehold.co/400x260/25D366/ffffff?text=Diseño' }, time: t('14', '05'), status: 'read' },
                        { id: 5, direction: 'inbound', type: 'audio', media: { url: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3' }, time: t('14', '06'), status: 'read' },
                        { id: 6, direction: 'outbound', sender: 'human', type: 'document', media: { url: '#', filename: 'cotizacion-tarjetas.pdf', meta: 'PDF · 128 KB' }, time: t('14', '10'), status: 'delivered',
                          replyTo: { author: 'Cliente', preview: 'Este es el diseño que tengo' } },
                        { id: 7, direction: 'inbound', type: 'location', location: { lat: '-17.7833', lng: '-63.1821' }, time: t('14', '12'), status: 'read' },
                        { id: 8, direction: 'inbound', type: 'contact', contact: { name: 'Juan Pérez', phone: '+591 700 00000' }, time: t('14', '12'), status: 'read' },
                        { id: 9, direction: 'outbound', sender: 'human', type: 'text', text: 'Perfecto, te envío la cotización. ¿Confirmamos?', time: t('14', '15'), status: 'sent' },
                    ];
                    this.aiEnabled = true;
                    // The mock showcases every type; real capabilities come from
                    // the server, which only reports what a driver implements.
                    this.capabilities = {
                        send: ['text', 'image', 'document', 'audio', 'reply'],
                        receive: ['text', 'image', 'document', 'audio'],
                    };
                    this.scrollToBottom();
                },

                startReal() {
                    if (!this.context.phone && !this.context.personId && !this.context.leadId) return;
                    this.isLoading = true;
                    this.fetchThread(true).finally(() => {
                        this.isLoading = false;
                        // poll for incoming (until Reverb replaces this in HU-05)
                        this.pollTimer = setInterval(() => this.fetchThread(false), 4000);
                    });
                },

                fetchThread(initial) {
                    return this.$axios.get(this.threadUrl, {
                            params: {
                                phone: this.context.phone,
                                person_id: this.context.personId,
                                lead_id: this.context.leadId,
                                since: initial ? null : this.since,
                            },
                        })
                        .then(res => {
                            if (res.data?.server_time) this.since = res.data.server_time;
                            if (res.data?.capabilities) this.capabilities = res.data.capabilities;
                            if (res.data?.window) this.window = res.data.window;
                            if (res.data?.conversation?.id) this.conversationId = res.data.conversation.id;
                            if (res.data?.agent) this.aiEnabled = res.data.agent.effective;

                            const incoming = res.data?.messages ?? [];
                            if (!incoming.length) return;

                            if (initial) {
                                this.messages = incoming;
                            } else {
                                for (const m of incoming) {
                                    const i = this.messages.findIndex(x => x.id === m.id);
                                    if (i === -1) this.messages.push(m);
                                    else this.messages.splice(i, 1, m);
                                }
                            }
                            this.scrollToBottom();
                        })
                        .catch(() => {});
                },

                send() {
                    const text = this.draft.trim();
                    if (!text) return;
                    this.draft = '';

                    if (this.useMock) {
                        const msg = {
                            id: Date.now(), direction: 'outbound', sender: 'human', type: 'text',
                            text, time: this.now(), date: this.today(), status: 'queued', replyTo: this.replyingTo,
                        };
                        this.messages.push(msg);
                        this.replyingTo = null;
                        this.scrollToBottom();
                        setTimeout(() => { msg.status = 'sent'; }, 500);
                        setTimeout(() => { msg.status = 'delivered'; }, 1200);
                        return;
                    }

                    this.sendReal(text);
                },

                sendReal(text) {
                    const tempId = 'tmp-' + Date.now();
                    const replyId = this.replyingTo?.id ?? null;
                    this.messages.push({
                        id: tempId, direction: 'outbound', sender: 'human', type: 'text',
                        text, time: this.now(), date: this.today(), status: 'queued', replyTo: this.replyingTo,
                    });
                    this.replyingTo = null;
                    this.scrollToBottom();

                    this.$axios.post(this.sendUrl, {
                            phone: this.context.phone,
                            person_id: this.context.personId,
                            lead_id: this.context.leadId,
                            name: this.context.name,
                            text,
                            reply_to_id: replyId,
                        })
                        .then(res => {
                            const real = res.data?.message;
                            const i = this.messages.findIndex(m => m.id === tempId);
                            if (real && i !== -1) this.messages.splice(i, 1, real);
                        })
                        .catch(() => {
                            const i = this.messages.findIndex(m => m.id === tempId);
                            if (i !== -1) this.messages[i].status = 'failed';
                        });
                },

                mockAttach(type) {
                    this.showAttach = false;
                    const samples = {
                        image: { type: 'image', media: { url: 'https://placehold.co/320x200/075E54/ffffff?text=Adjunto' } },
                        document: { type: 'document', media: { url: '#', filename: 'archivo.pdf', meta: 'PDF · 64 KB' } },
                        audio: { type: 'audio', media: { url: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3' } },
                    };
                    this.messages.push({
                        id: Date.now(), direction: 'outbound', sender: 'human',
                        time: this.now(), date: this.today(), status: 'sent', ...samples[type],
                    });
                    this.scrollToBottom();
                },

                statusTick(status) {
                    return { queued: '🕓', sent: '✓', delivered: '✓✓', read: '✓✓', failed: '⚠' }[status] || '';
                },

                formatText(s) {
                    if (!s) return '';
                    return String(s)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/\n/g, '<br>');
                },

                now() {
                    const d = new Date();
                    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
                },

                today() {
                    const d = new Date();
                    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                },

                dayLabel(date) {
                    if (!date) return '';
                    const today = new Date(); today.setHours(0, 0, 0, 0);
                    const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
                    // date is 'Y-m-d'; parse as local midnight
                    const [y, m, d] = date.split('-').map(Number);
                    const dt = new Date(y, m - 1, d);
                    if (dt.getTime() === today.getTime()) return 'Hoy';
                    if (dt.getTime() === yesterday.getTime()) return 'Ayer';
                    return dt.toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const c = this.$refs.scroll;
                        if (c) c.scrollTop = c.scrollHeight;
                    });
                },
            },
        });
    </script>
@endPushOnce
