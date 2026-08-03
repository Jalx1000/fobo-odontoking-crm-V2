<v-lookup {{ $attributes }}></v-lookup>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-lookup-template"
    >
        <div
            class="relative"
            ref="lookup"
        >
            <!-- Input Box (Button) -->
            <div
                class="relative inline-block w-full"
                @click="toggle"
                @keydown.enter.prevent="toggle"
                @keydown.space.prevent="toggle"
                @keydown.esc="close"
                tabindex="0"
                role="combobox"
                :aria-expanded="showPopup"
                aria-haspopup="listbox"
            >
                <!-- Input Container -->
                <div class="relative flex cursor-pointer items-center justify-between rounded border border-gray-200 p-2 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:text-gray-300">
                    <!-- Selected Item or Placeholder Text -->
                    <span
                        class="overflow-hidden text-ellipsis"
                        :title="selectedItem?.name"
                    >
                        @{{ selectedItem?.name !== "" ? selectedItem?.name : "@lang('admin::app.components.lookup.click-to-add')" }}
                    </span>

                    <!-- Icons Container -->
                    <div class="flex items-center gap-2">
                        <!-- Close Icon -->
                        <i
                            v-if="(selectedItem?.name) && ! isSearching"
                            class="icon-cross-large cursor-pointer text-xl text-gray-600"
                            @click.stop="remove"
                            aria-hidden="true"
                        ></i>

                        <!-- Arrow Icon -->
                        <i
                            class="text-2xl text-gray-600"
                            :class="showPopup ? 'icon-up-arrow' : 'icon-down-arrow'"
                            aria-hidden="true"
                        ></i>
                    </div>
                </div>
            </div>

            <!-- Hidden Input Box -->
            <x-admin::form.control-group.control
                type="hidden"
                ::name="name"
                ::rules="rules"
                ::label="label"
                v-model="selectedItem.id"
            />

            <!--
                Popup Box.

                Teleported to the body so it is not clipped by ancestors with `overflow-hidden`
                (modals, drawers) nor trapped inside their stacking/transform contexts.
                Positioning is therefore fixed and recalculated from the trigger's rect.
            -->
            <Teleport to="body">
                <div
                    v-if="showPopup"
                    ref="popup"
                    :style="popupStyle"
                    class="fixed z-[10010] flex flex-col gap-2 overflow-hidden rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-900 dark:bg-gray-800"
                    @keydown.esc="close"
                >
                    <!-- Search Bar -->
                    <div class="relative flex shrink-0 items-center">
                        <input
                            type="text"
                            v-model.lazy="searchTerm"
                            v-debounce="500"
                            class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 focus-visible:ring-2 focus-visible:ring-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                            placeholder="@lang('admin::app.components.lookup.search')"
                            ref="searchInput"
                            @keyup="search"
                        />

                        <!-- Search Icon (absolute positioned) -->
                        <span class="absolute flex items-center ltr:right-2 rtl:left-2">
                            <!-- Loader (optional, based on condition) -->
                            <div
                                class="relative"
                                v-if="isSearching"
                            >
                                <x-admin::spinner />
                            </div>
                        </span>
                    </div>

                    <!-- Results List -->
                    <ul
                        class="min-h-0 flex-1 divide-y divide-gray-100 overflow-y-auto"
                        role="listbox"
                    >
                        <li
                            v-for="item in filteredResults"
                            :key="item.id"
                            class="cursor-pointer px-4 py-2 text-gray-800 transition-colors hover:bg-blue-100 dark:text-white dark:hover:bg-gray-900"
                            @click="selectItem(item)"
                            role="option"
                        >
                            @{{ item.name }}
                        </li>

                        <template v-if="filteredResults.length === 0">
                            <li class="px-4 py-2 text-gray-500">
                                @lang('admin::app.components.lookup.no-results')
                            </li>

                            <li
                                v-if="searchTerm.length > 2 && canAddNew"
                                @click="selectItem({ id: '', name: searchTerm })"
                                class="cursor-pointer border-t border-gray-800 px-4 py-2 text-gray-500 hover:bg-brandColor hover:text-white dark:border-gray-300 dark:text-gray-400 dark:hover:bg-gray-900 dark:hover:text-white"
                            >
                                <i class="icon-add text-md" aria-hidden="true"></i>

                                @lang('admin::app.components.lookup.add-as-new')
                            </li>
                        </template>
                    </ul>
                </div>
            </Teleport>
        </div>
    </script>

    <script type="module">
        app.component('v-lookup', {
            template: '#v-lookup-template',

            props: {
                src: {
                    type: String,
                    required: true,
                },

                params: {
                    type: Object,
                    default: () => ({}),
                },

                name: {
                    type: String,
                    required: true,
                },

                placeholder: {
                    type: String,
                    required: true,
                },

                value: {
                    type: Object,
                    default: () => ({}),
                },

                rules: {
                    type: String,
                    default: '',
                },

                label: {
                    type: String,
                    default: '',
                },

                canAddNew: {
                    type: Boolean,
                    default: false,
                },

                preload: {
                    type: Boolean,
                    default: false,
                }
            },

            emits: ['on-selected'],

            data() {
                return {
                    showPopup: false,

                    searchTerm: '',

                    selectedItem: {},

                    searchedResults: [],

                    isSearching: false,

                    cancelToken: null,

                    popupStyle: {},

                    /**
                     * Search input + results list at their natural size. Used only to decide
                     * whether the popup fits below the trigger.
                     */
                    preferredHeight: 260,
                };
            },

            mounted() {
                if (this.value) {
                    this.selectedItem = this.value;
                }

                this.search(this.preload);
            },

            created() {
                window.addEventListener('click', this.handleFocusOut);
            },

            beforeUnmount() {
                window.removeEventListener('click', this.handleFocusOut);

                this.unbindReposition();
            },

            watch: {
                searchTerm(newVal, oldVal) {
                    this.search(this.preload);
                },

                showPopup(isOpen) {
                    if (isOpen) {
                        this.updatePopupPosition();

                        /**
                         * Capture phase so scrolling any ancestor (modal body, drawer,
                         * page) keeps the popup glued to its trigger.
                         */
                        window.addEventListener('scroll', this.updatePopupPosition, true);
                        window.addEventListener('resize', this.updatePopupPosition);
                    } else {
                        this.unbindReposition();
                    }
                },
            },

            computed: {
                /**
                 * Filter the searchedResults based on the search query.
                 *
                 * @return {Array}
                 */
                filteredResults() {
                    const term = (this.searchTerm || '').toLowerCase();

                    return this.searchedResults.filter(item =>
                        (item.name || '').toLowerCase().includes(term)
                    );
                }
            },

            methods: {
                /**
                 * Toggle the popup.
                 *
                 * @return {void}
                 */
                toggle() {
                    this.showPopup = ! this.showPopup;

                    if (this.showPopup) {
                        this.$nextTick(() => this.$refs.searchInput?.focus());
                    }
                },

                /**
                 * Close the popup.
                 *
                 * @return {void}
                 */
                close() {
                    this.showPopup = false;
                },

                /**
                 * Anchor the teleported popup to the trigger, flipping it above when there
                 * is not enough room below (a lookup at the bottom of a modal is the common
                 * case). The available space also caps the height so it never overflows the
                 * viewport.
                 *
                 * @return {void}
                 */
                updatePopupPosition() {
                    const trigger = this.$refs.lookup;

                    if (! trigger) {
                        return;
                    }

                    const rect = trigger.getBoundingClientRect();

                    const gap = 4;

                    const spaceBelow = window.innerHeight - rect.bottom - gap;

                    const spaceAbove = rect.top - gap;

                    const style = {
                        left: `${rect.left}px`,
                        width: `${rect.width}px`,
                    };

                    if (
                        spaceBelow < this.preferredHeight
                        && spaceAbove > spaceBelow
                    ) {
                        style.bottom = `${window.innerHeight - rect.top + gap}px`;

                        style.maxHeight = `${Math.min(spaceAbove, this.preferredHeight)}px`;
                    } else {
                        style.top = `${rect.bottom + gap}px`;

                        style.maxHeight = `${Math.min(spaceBelow, this.preferredHeight)}px`;
                    }

                    this.popupStyle = style;
                },

                /**
                 * Detach the reposition listeners.
                 *
                 * @return {void}
                 */
                unbindReposition() {
                    window.removeEventListener('scroll', this.updatePopupPosition, true);

                    window.removeEventListener('resize', this.updatePopupPosition);
                },

                /**
                 * Select an item from the list.
                 *
                 * @param {Object} item
                 *
                 * @return {void}
                 */
                selectItem(item) {
                    this.showPopup = false;

                    this.searchTerm = '';

                    this.selectedItem = item;

                    this.$emit('on-selected', item);
                },

                /**
                 * Initialize the items.
                 *
                 * @return {void}
                 */
                search(preload = false) {
                    if (
                        ! preload
                        && this.searchTerm.length <= 2
                    ) {
                        this.searchedResults = [];

                        this.isSearching = false;

                        return;
                    }

                    this.isSearching = true;

                    if (this.cancelToken) {
                        this.cancelToken.cancel();
                    }

                    this.cancelToken = this.$axios.CancelToken.source();

                    this.$axios.get(this.src, {
                            params: {
                                ...this.params,
                                query: this.searchTerm
                            },
                            cancelToken: this.cancelToken.token,
                        })
                        .then(response => {
                            this.searchedResults = response.data.data;
                        })
                        .catch(error => {
                            if (! this.$axios.isCancel(error)) {
                                console.error("Search request failed:", error);
                            }

                            this.isSearching = false;
                        })
                        .finally(() => this.isSearching = false);
                },

                /**
                 * Handle the focus out event.
                 *
                 * @param {Event} event
                 *
                 * @return {void}
                 */
                handleFocusOut(event) {
                    const lookup = this.$refs.lookup;

                    /**
                     * The popup lives in the body now, so it is no longer a descendant of
                     * the trigger and has to be tested separately.
                     */
                    const popup = this.$refs.popup;

                    if (
                        lookup
                        && ! lookup.contains(event.target)
                        && ! popup?.contains(event.target)
                    ) {
                        this.showPopup = false;
                    }
                },

                /**
                 * Remove the selected item.
                 *
                 * @return {void}
                 */
                remove() {
                    this.selectedItem = {
                        id: '',
                        name: '',
                    };

                    this.$emit('on-selected', {});
                }
            },
        });
    </script>
@endPushOnce
