@if (isset($attribute))
    @php
        $lookUpEntityData = app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpEntity($attribute->lookup_type, old($attribute->code) ?: $value);
    @endphp

    <v-lookup-component
        :attribute="{{ json_encode($attribute) }}"
        :validations="'{{ $validations }}'"
        :value="{{ json_encode($lookUpEntityData)}}"
        can-add-new="{{ $canAddNew ?? false }}"
        placeholder="{{ $placeholder ?? '' }}"
        @lookup-added="handleLookupAdded"
        @lookup-removed="handleLookupRemoved"
    >
        <div class="relative inline-block w-full">
            <!-- Input Container -->
            <div class="relative flex items-center justify-between rounded border border-gray-200 p-2 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:text-gray-300">
                <span
                    class="text-gray-600 dark:text-gray-300"
                    v-text="selectedItem.name ?? placeholder"
                ></span>

                <!-- Icons Container -->
                <div class="flex items-center gap-2">
                    <!-- Arrow Icon -->
                    <i class="icon-down-arrow text-2xl text-gray-600"></i>
                </div>
            </div>
        </div>
    </v-lookup-component>
@endif

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-lookup-component-template"
    >
        <div
            class="relative"
            ref="lookup"
        >
            <div
                class="relative inline-block w-full"
                @click="toggle"
            >
                <!-- Input Container -->
                <div
                    class="relative flex items-center justify-between rounded border border-gray-200 p-2 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:text-gray-300"
                    :class="{
                        'bg-gray-50': isDisabled,
                    }"
                >
                    <!-- Selected Item or Placeholder Text -->
                    <span
                        class="overflow-hidden text-ellipsis text-gray-600 dark:text-gray-300"
                        :title="selectedItem?.name"
                    >
                        @{{ selectedItem?.name !== "" ? selectedItem?.name : (placeholder || "@lang('admin::app.components.attributes.lookup.click-to-add')") }}
                    </span>

                    <!-- Icons Container -->
                    <div class="flex items-center gap-2">
                        <!-- Close Icon -->
                        <i
                            v-if="
                                ! isDisabled
                                && (
                                    selectedItem?.name
                                    && ! isSearching
                                )"
                            class="icon-cross-large cursor-pointer text-2xl text-gray-600"
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

            <!-- Hidden Input Entity Value -->
            <x-admin::form.control-group.control
                type="hidden"
                ::name="attribute['code']"
                v-model="selectedItem.id"
                ::rules="validations"
                ::label="attribute['name']"
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
                        <!-- Input Box -->
                        <input
                            type="text"
                            v-model.lazy="searchTerm"
                            v-debounce="500"
                            class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 focus-visible:ring-2 focus-visible:ring-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                            placeholder="@lang('admin::app.components.attributes.lookup.search')"
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
                            class="flex cursor-pointer gap-2 p-2 transition-colors hover:bg-blue-100 dark:text-gray-300 dark:hover:bg-gray-900"
                            @click="handleResult(item)"
                            role="option"
                        >
                            <!-- Entity Name -->
                            <span>@{{ item.name }}</span>
                        </li>

                        <template v-if="filteredResults.length === 0">
                            <li class="px-4 py-2 text-center text-gray-500">
                                @lang('admin::app.components.attributes.lookup.no-result-found')
                            </li>

                            <li
                                v-if="searchTerm.length > 2 && canAddNew"
                                @click="handleResult({ id: '', name: searchTerm })"
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
        app.component('v-lookup-component', {
            template: '#v-lookup-component-template',

            props: ['validations', 'isDisabled', 'attribute', 'value', 'canAddNew', 'placeholder'],

            emits: ['lookup-added', 'lookup-removed'],

            data() {
                return {
                    showPopup: false,

                    searchTerm: '',

                    searchedResults: [],

                    selectedItem: {
                        id: '',
                        name: ''
                    },

                    searchRoute: `{{ route('admin.settings.attributes.lookup') }}/${this.attribute.lookup_type}`,

                    lookupEntityRoute: `{{ route('admin.settings.attributes.lookup_entity') }}/${this.attribute.lookup_type}`,

                    isSearching: false,

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
                    this.getLookUpEntity();
                }

                window.addEventListener('click', this.handleFocusOut);
            },

            beforeUnmount() {
                window.removeEventListener('click', this.handleFocusOut);

                this.unbindReposition();
            },

            watch: {
                searchTerm(newVal, oldVal) {
                    this.search();
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
                toggle() {
                    if (this.isDisabled) {
                        this.showPopup = false;

                        return;
                    }

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

                search() {
                    if (this.searchTerm.length <= 2) {
                        this.searchedResults = [];

                        this.isSearching = false;

                        return;
                    }

                    this.isSearching = true;

                    this.$axios.get(this.searchRoute, {
                            params: { query: this.searchTerm }
                        })
                        .then (response => {
                            this.searchedResults = response.data;
                        })
                        .catch (error => {})
                        .finally(() => this.isSearching = false);
                },

                getLookUpEntity() {
                    this.$axios.get(this.lookupEntityRoute, {
                            params: { query: this.value?.id ?? ""}
                        })
                        .then (response => {
                            this.selectedItem = Object.keys(response.data).length
                                ? response.data
                                : {
                                    id: '',
                                    name: ''
                                };
                        })
                        .catch (error => {});
                },

                handleResult(result) {
                    this.showPopup = false;

                    this.selectedItem = result;

                    this.searchTerm = '';

                    this.$emit('lookup-added', this.selectedItem);
                },

                handleFocusOut(e) {
                    const lookup = this.$refs.lookup;

                    /**
                     * The popup lives in the body now, so it is no longer a descendant of
                     * the trigger and has to be tested separately.
                     */
                    const popup = this.$refs.popup;

                    if (
                        lookup
                        && ! lookup.contains(e.target)
                        && ! popup?.contains(e.target)
                    ) {
                        this.showPopup = false;
                    }
                },

                remove() {
                    this.selectedItem = {
                        id: '',
                        name: ''
                    };

                    this.$emit('lookup-removed', this.selectedItem);
                },
            },
        });
    </script>
@endPushOnce
