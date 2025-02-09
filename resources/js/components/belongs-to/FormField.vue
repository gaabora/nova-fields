<template>
  <r64-default-field
    :hide-field="hideField"
    :field="field"
    :hide-label="hideLabelInForms"
    :field-classes="fieldClasses"
    :wrapper-classes="wrapperClasses"
    :label-classes="labelClasses"
  >
    <template slot="field">
      <loading-view :loading="loading">
        <div class="flex items-center">
          <search-input
            v-if="isSearchable && !isLocked"
            :data-testid="`${field.resourceName}-search-input`"
            @input="performSearch"
            @clear="clearSelection"
            @selected="selectResource"
            :error="hasError"
            :value="selectedResource"
            :data="availableResources"
            :clearable="isClearable"
            trackBy="value"
            searchBy="display"
            class="flex-grow"
          >
            <div
              slot="default"
              v-if="selectedResource"
              class="flex items-center"
            >
              <div
                v-if="selectedResource.avatar"
                class="mr-3"
              >
                <img
                  :src="selectedResource.avatar"
                  class="w-8 h-8 rounded-full block"
                >
              </div>
              {{ selectedResource.display }}
            </div>

            <div
              slot="option"
              slot-scope="{option, selected}"
              class="flex items-center"
            >
              <div
                v-if="option.avatar"
                class="mr-3"
              >
                <img
                  :src="option.avatar"
                  class="w-8 h-8 rounded-full block"
                >
              </div>
              {{ option.display }}
            </div>
          </search-input>

          <select
            v-if="!isSearchable || isLocked"
            :class="[inputClasses, { 'border-danger': hasError }]"
            :data-testid="`${field.resourceName}-select`"
            :dusk="field.attribute"
            @change="selectResourceFromSelectControl"
            :disabled="isLocked"
          >
            <option
              value
              :disabled="!isClearable"
              :selected="selectedResourceId == null"
            >{{ placeholder }}</option>

            <option
              v-for="resource in options"
              :key="resource.value"
              :value="resource.value"
              :selected="selectedResourceId == resource.value"
              :disabled="resource.disabled"
            >{{ resource.display}}</option>
          </select>
          <a
            v-if="field.quickEdit && !isModal && selectedResourceId"
            class="btn btn-white p-2 rounded ml-3 cursor-pointer"
            @click="openModalEdit = true"
          >❐</a>
          <a
            v-if="field.quickCreate && !isModal"
            class="btn btn-primary p-2 rounded ml-3 cursor-pointer"
            @click="openModalCreate = true"
          >+</a>
        </div>
      </loading-view>

      <portal
        v-if="!isModal"
        to="modals"
      >
        <transition name="fade">
          <ModalCreate
            v-if="openModalCreate"
            :resourceName="field.resourceName"
            :fillValues="field.fillValues"
            @confirm="reloadResources"
            @close="openModalCreate = false"
          />
          <ModalEdit
            v-if="openModalEdit"
            :resourceId="selectedResourceId"
            :resourceName="field.resourceName"
            :fillValues="field.fillValues"
            @confirm="reloadResources"
            @close="openModalEdit = false"
          />
        </transition>
      </portal>
      <!-- Trashed State -->
      <div v-if="softDeletes && !isLocked && !field.disableTrashed">
        <label
          class="flex items-center"
          @input="toggleWithTrashed"
          @keydown.prevent.space.enter="toggleWithTrashed"
        >
          <checkbox
            :dusk="field.resourceName + '-with-trashed-checkbox'"
            :checked="withTrashed"
          />

          <span class="ml-2">{{__('With Trashed')}}</span>
        </label>
      </div>

      <p
        v-if="hasError"
        class="my-2 text-danger"
      >{{ firstError }}</p>
    </template>
  </r64-default-field>
</template>

<script>
import storage from '../../storage/BelongsToFieldStorage'
import {
  TogglesTrashed,
  PerformsSearches,
  HandlesValidationErrors
} from 'laravel-nova'
import ModalCreate from '../../modals/ModalCreate'
import ModalEdit from '../../modals/ModalEdit'
import R64Field from '../../mixins/R64Field'

export default {
  components: { ModalCreate, ModalEdit },

  mixins: [TogglesTrashed, PerformsSearches, HandlesValidationErrors, R64Field],

  props: {
    isModal: {
      type: Boolean,
      default: false
    },
    resourceName: String,
    field: Object,
    viaResource: {},
    viaResourceId: {},
    viaRelationship: {},
    fillValues: {},
    dataSet: Array,
    withDataSet: false
  },

  data: () => ({
    loading: false,
    openModalCreate: false,
    openModalEdit: false,
    availableResources: [],
    initializingWithExistingResource: false,
    selectedResource: null,
    selectedResourceId: null,
    softDeletes: false,
    withTrashed: false,
    resourceUriKey: '',
    search: ''
  }),

  computed: {
    options() {
      if (!this.field.grouped) {
        return this.availableResources
      }

      const options = []
      let lastGroup = ''
      _.sortBy(this.availableResources, 'groupedBy').forEach(resource => {
        if (resource.groupedBy !== lastGroup) {
          options.push({
            disabled: true,
            value: '',
            display: resource.groupedBy
          })
          lastGroup = resource.groupedBy
        }

        options.push(resource)
      })
      return options
    },

    /**
     * Get the placeholder.
     */
    placeholder() {
      return this.field.placeholder === undefined
        ? `${this.__('Choose')} ${this.field.name}`
        : this.field.placeholder
    },

    /**
     * Determine if we are editing and existing resource
     */
    editingExistingResource() {
      return Boolean(this.field.belongsToId)
    },

    /**
     * Determine if we are creating a new resource via a parent relation
     */
    creatingViaRelatedResource() {
      return this.viaResource == this.field.resourceName && this.viaResourceId
    },

    /**
     * Determine if we should select an initial resource when mounting this field
     */
    shouldSelectInitialResource() {
      return Boolean(
        this.editingExistingResource || this.creatingViaRelatedResource
      )
    },

    shouldPrepopulate() {
      return Boolean(
        this.field.prepopulate
      )
    },
    /**
     * Determine if the related resources is searchable
     */
    isSearchable() {
      return this.field.searchable
    },
    /**
     * Determine if the field is clearable
     */
    isClearable () {
      return this.field.nullable && this.field.clearable
    },

    /**
     * Get the query params for getting available resources
     */
    queryParams() {
      return {
        params: {
          current: this.selectedResourceId,
          first: this.initializingWithExistingResource,
          search: this.search,
          withTrashed: this.withTrashed,
          prepopulate: this.shouldPrepopulate,
          prepopulateParams: this.field.prepopulateParams,
          relatableParams: this.field.relatableParams
        }
      }
    },

    isLocked() {
      return this.viaResource == this.field.resourceName || this.readOnly
    }
  },

  watch: {
    /**
     * Setting the dataSet within row component
     */
    dataSet(data) {
      this.availableResources = data
    }
  },

  /**
   * Mount the component.
   */
  mounted() {
    this.resourceUriKey = (this.field.resourceUriKey) ? this.field.resourceUriKey : this.resourceName
    this.initializeComponent()
  },

  methods: {
    async reloadResources(id) {
      this.loading = true
      await this.getAvailableResources()
      if (id) {
        this.selectedResourceId = id
        this.selectInitialResource()
      }
      this.openModalCreate = false
      this.openModalEdit = false
      this.loading = false
    },
    clearSelection() {
      // local extension of default laravel-nova/src/mixins/PerformsSearches.js
      this.selectedResource = null
      this.selectedResourceId = null
      if (this.isSearchable && !this.shouldPrepopulate) {
        this.availableResources = []
      }
    },
    selectResource(resource) {
      // local extension of default laravel-nova/src/mixins/PerformsSearches.js
      this.selectedResource = resource
      this.selectedResourceId = resource.value
    },
    performSearch(search) {
      // local extension of default laravel-nova/src/mixins/PerformsSearches.js
      this.search = search

      const trimmedSearch = search.trim()
      // If the user performs an empty search, it will load all the results
      // so let's just set the availableResources to an empty array to avoid
      // loading a huge result set
      if (trimmedSearch == '') {
        this.clearSelection()
        
        if (!this.shouldPrepopulate) return
      }

      this.debouncer(() => {
        this.selectedResource = ''
        this.getAvailableResources(trimmedSearch)
      }, 500)
    },

    initializeComponent() {

      this.withTrashed = false

      // If a user is editing an existing resource with this relation
      // we'll have a belongsToId on the field, and we should prefill
      // that resource in this field
      if (this.editingExistingResource) {
        this.initializingWithExistingResource = true
        this.selectedResourceId = this.field.belongsToId
      }

      // If the user is creating this resource via a related resource's index
      // page we'll have a viaResource and viaResourceId in the params and
      // should prefill the resource in this field with that information
      if (this.creatingViaRelatedResource) {
        this.initializingWithExistingResource = true
        this.selectedResourceId = this.viaResourceId
      }

      if (this.shouldSelectInitialResource && !this.isSearchable) {
        // If we should select the initial resource but the field is not
        // searchable we should load all of the available resources into the
        // field first and select the initial option
        this.initializingWithExistingResource = false
        this.getAvailableResources().then(() => this.selectInitialResource())
      } else if (this.shouldSelectInitialResource && this.isSearchable) {
        // If we should select the initial resource and the field is
        // searchable, we won't load all the resources but we will select
        // the initial option
        this.getAvailableResources().then(() => this.selectInitialResource())
      } else if (
        !this.shouldSelectInitialResource &&
        !this.isSearchable &&
        !this.withDataSet
      ) {
        // If we don't need to select an initial resource because the user
        // came to create a resource directly and there's no parent resource,
        // and the field is searchable we'll just load all of the resources
        this.getAvailableResources()
      } else if (this.withDataSet && this.dataSet.length) {
        // If it is within a row component and exists previous dataSet
        // it will populate the availableResources with dataSet
        this.availableResources = this.dataSet
      } else if (this.isSearchable && this.shouldPrepopulate) {
        this.getAvailableResources()
      }

      if (!this.withDataSet && !this.field.disableTrashed) {
        // If it is within a row component and it is the 0 index this will be called
        this.determineIfSoftDeletes()
      }

      this.field.fill = this.fill

      this.registerDependencyWatchers(this.$root)
    },

    registerDependencyWatchers(root) {
      if(this.field.dependsOn === undefined) {
        return;
      }
      root.$children.forEach(component => {
        if (this.componentIsDependency(component)) {
          component.$watch('selectedResourceId', (value) => {
            let dependencyField = component.field.attribute
            this.field.relatableParams[dependencyField] = value
            // set fillValues for quickCreate() if needed
            if (this.field.fillValues && this.field.fillValues[dependencyField]) {
              if (this.field.fillValues[dependencyField].belongsToId != value) {
                this.field.fillValues[dependencyField].belongsToId = value
              }
            }

            if (this.isSearchable && !this.shouldPrepopulate) {
              this.selectInitialResource()
              this.availableResources = []
            } else {
              this.getAvailableResources().then(() => this.selectInitialResource())
            }
          }, {immediate: false})
        }
        this.registerDependencyWatchers(component)
      })
    },
    componentIsDependency(component) {
      if(component.field === undefined) {
        return false;
      }
      for (let dependencyField of this.field.dependsOn) {
        if(component.field.attribute === dependencyField) {
          return true;
        }
      }
      return false;
    },

    /**
     * Select a resource using the <select> control
     */
    selectResourceFromSelectControl(e) {
      this.selectedResourceId = (e.target.value) ? e.target.value : null
      this.$emit('input', this.selectedResourceId)
      this.selectInitialResource()
    },

    /**
     * Fill the forms formData with details from this field
     */
    fill(formData) {
      if (this.selectedResource) {
        formData.append(this.field.attribute, this.selectedResource.value)
        formData.append(this.field.attribute + '_trashed', this.withTrashed)
      } else if (this.field.nullable) {
        formData.append(this.field.attribute, '')
      }
    },

    /**
     * Get the resources that may be related to this resource.
     */
    getAvailableResources() {
      return storage
        .fetchAvailableResources(
          this.resourceUriKey,
          this.field.attribute,
          this.queryParams
        )
        .then(({ data: { resources, softDeletes, withTrashed } }) => {
          if (this.initializingWithExistingResource || !this.isSearchable) {
            this.withTrashed = withTrashed
          }

          // Turn off initializing the existing resource after the first time
          this.initializingWithExistingResource = false
          this.availableResources = resources
          this.softDeletes = softDeletes

          // Set dataSet for others nova-field's BelongsTo into nova-field's Row
          if (!this.withDataSet) {
            this.$emit('data-set-available', resources)
          }
        })
    },

    /**
     * Determine if the relatd resource is soft deleting.
     */
    determineIfSoftDeletes() {
      return storage
        .determineIfSoftDeletes(this.field.resourceName)
        .then(response => {
          this.softDeletes = response.data.softDeletes
        })
    },

    /**
     * Determine if the given value is numeric.
     */
    isNumeric(value) {
      return !isNaN(parseFloat(value)) && isFinite(value)
    },

    /**
     * Select the initial selected resource
     */
    selectInitialResource() {
      this.selectedResource = _.find(
        this.availableResources,
        r => r.value == this.selectedResourceId
      )

      if (this.selectedResource === undefined) {
        this.selectedResource = null
        this.selectedResourceId = null
      }
      this.$emit('input', this.selectedResourceId)
    },

    /**
     * Toggle the trashed state of the search
     */
    toggleWithTrashed() {
      this.withTrashed = !this.withTrashed

      // Reload the data if the component doesn't support searching
      if (!this.isSearchable) {
        this.getAvailableResources()
      }
    },

    /**
     * Update the field's internal value.
     */
    handleChange(item) {
      this.selectedResourceId = item.id

      this.$nextTick(this.selectInitialResource)
    }
  }
}
</script>
