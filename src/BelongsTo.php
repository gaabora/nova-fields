<?php

namespace R64\NovaFields;

use Laravel\Nova\Rules\Relatable;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\BelongsTo as NovaBelongsTo;

class BelongsTo extends NovaBelongsTo
{
    use Configurable;

    /**
     * The base input classes of the field.
     *
     * @var string
     */
    public $inputClasses = 'w-full form-control form-select form-input form-input-bordered';

    /**
     * The base index classes of the field.
     *
     * @var string
     */
    public $indexClasses = 'no-underline dim text-primary font-bold';

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-fields-belongs-to';

    /**
     * The field used for display the element inside a Row Field.
     *
     * @var string
     */
    public $displayName = 'name';

    
    /**
     * Determine if this field can be cleared (if it is also nullable)
     *
     * @var bool
     */
    public $clearable = true;

    /**
     * Determine if this field should skip Relatable rule if used in Row / JSON
     *
     * @var bool
     */
    public $disableRelatableRule = false;

    protected $groupedBy = null;

    protected $prepopulate = false;
    protected $prepopulateParams = null;
    protected $relatableParams = null;

    /**
     * Format the given associatable resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  mixed  $resource
     * @return array
     */
    public function formatAssociatableResource(NovaRequest $request, $resource)
    {
        $relation = explode(".", $this->groupedBy);

        $groupedBy = count($relation) == 2
                   ? $resource->{$relation[0]}->{$relation[1]}
                   : $resource->{$this->groupedBy};

        return array_filter([
            'groupedBy' => $groupedBy,
            'avatar' => $resource->resolveAvatarUrl($request),
            'display' => $this->formatDisplayValue($resource),
            'value' => $resource->getKey(),
        ]);
    }

    /**
     * Get the validation rules for this field.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function getRules(NovaRequest $request)
    {
        $rules = parent::getRules($request);

        return [
            $this->attribute => array_filter($rules[$this->attribute], function ($rule) {
                if ($rule instanceof Relatable) {
                    return !$this->disableRelatableRule;
                }
                return true;
            })
        ];
    }

    /**
     * Set the field that should be used to group the resources.
     *
     * @param  string  $field
     * @return $this
     */
    public function groupedBy($field)
    {
        $this->groupedBy = $field;

        return $this;
    }

    /**
     * Set the field that should be used to be displayed in a Row Field.
     *
     * @param  string  $name
     * @return $this
     */
    public function displayName($name)
    {
        $this->displayName = $name;

        return $this;
    }

    /**
     * Indicate that the field should be clearable.
     *
     * @param  bool $clearable
     * @return $this
     */
    public function clearable($clearable = true)
    {
        $this->clearable = $clearable;

        return $this;
    }

    /**
     * Determine if this field should skip Relatable rule if used in Row / JSON
     *
     * @return $this
     */
    public function disableRelatableRule()
    {
        $this->disableRelatableRule = true;

        return $this;
    }

    /**
     * Determine if a new related model can be created.
     *
     * @return $this
     */
    public function quickCreate($fillValues = [])
    {
        $this->withMeta([__FUNCTION__ => true, 'fillValues' => (object) $fillValues]);

        return $this;
    }

    /**
     * Determine if a related model can be edited.
     *
     * @return $this
     */
    public function quickEdit()
    {
        $this->withMeta([__FUNCTION__ => true]);

        return $this;
    }
    /**
     * Determine if a without trashed option should be hidden.
     *
     * @return $this
     */
    public function disableTrashed()
    {
        $this->withMeta([__FUNCTION__ => true]);

        return $this;
    }

    /**
     * Enable preloading options when using searchable()
     *
     * @return $this
     */
    public function prepopulate($params = null)
    {
        $this->prepopulate = true;
        if ($params) {
            $this->prepopulateParams = array_merge([
                'take' => 10,
            ], $params);
        } else {
            $this->prepopulateParams = [
                'take' => 10,
            ];
        }

        return $this;
    }

    /**
     * Determine a relatableQuery() params
     *
     * @return $this
     */
    public function setRelatableParams($params)
    {
        $this->relatableParams = $params;

        return $this;
    }


    /**
     * Determine a dependency fields
     *
     * @return $this
     */
    public function dependsOn($fields)
    {
        $this->withMeta([__FUNCTION__ => $fields]);

        return $this;
    }

    /**
     * Get additional meta information to merge with the field payload.
     *
     * @return array
     */
    public function meta()
    {
        return array_merge([
            'wrapperClasses' => $this->wrapperClasses,
            'indexClasses' => $this->indexClasses,
            'inputClasses' => $this->inputClasses,
            'fieldClasses' => $this->fieldClasses,
            'panelFieldClasses' => $this->panelFieldClasses,
            'labelClasses' => $this->labelClasses,
            'panelLabelClasses' => $this->panelLabelClasses,
            'panelWrapperClasses' => $this->panelWrapperClasses,
            'excerptClasses' => $this->excerptClasses,
            'grouped' => !!$this->groupedBy,
            'resourceName' => $this->resourceName,
            'label' => forward_static_call([$this->resourceClass, 'label']),
            'singularLabel' => forward_static_call([$this->resourceClass, 'singularLabel']),
            'belongsToRelationship' => $this->belongsToRelationship,
            'belongsToId' => $this->belongsToId,
            'searchable' => $this->searchable,
            'clearable' => $this->clearable,
            'displayName' => $this->displayName,
            'prepopulate' => $this->prepopulate,
            'prepopulateParams' => (object) $this->prepopulateParams,
            'relatableParams' => (object) $this->relatableParams,
        ], $this->meta);
    }
}
