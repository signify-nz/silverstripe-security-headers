<?php

namespace Signify\Forms\GridField;

use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extensible;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\FormAction;
use SilverStripe\View\SSViewer;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Core\Manifest\ModuleLoader;

/**
 * Adds an delete button to the bottom or top of a GridField.
 * Clicking the button opens a modal in which a user can select filter options.
 * The user can then delete models from the gridfield's list based on those filter options.
 */
class GridFieldDeleteRelationsButton implements GridField_HTMLProvider, GridField_ActionProvider, GridField_URLHandler
{
    use Injectable, Extensible;

    /**
     * Fragment to write the button to
     */
    protected $targetFragment;

    /**
     * @var GridField
     */
    protected $gridField;

    /**
     * A singleton of the class held by the gridfield
     * @var DataObject
     */
    protected $dummyObject;

    /**
     * @var string
     */
    protected $modalTitle = null;

    /**
     * @var FieldList
     */
    protected $filterFields;

    /**
     * @var array
     */
    protected $filterOptions = [
        '__default' => [
            'LessThan',
            'LessThanOrEqual',
            'GreaterThan',
            'GreaterThanOrEqual',
            'ExactMatch',
            'PartialMatch',
            'StartsWith',
            'EndsWith',
        ]
    ];

    const DEFAULT_OPTION = '__default';

    const OPTION_FIELD_SUFFIX = '__FilterOption';

    const FILTER_BY_SUFFIX = '__FilterBy';

    const FILTER_INVERT_SUFFIX = '__FilterInvert';

    /**
     * @param string $targetFragment The HTML fragment to write the button into
     */
    public function __construct($targetFragment = "after")
    {
        $this->targetFragment = $targetFragment;
    }

    /**
     * Place the export button in a <p> tag below the field
     *
     * @param GridField $gridField
     * @return array
     */
    public function getHTMLFragments($gridField)
    {
        $modalID = $gridField->ID() . '_DeleteRelationsModal';

        // Check for form message prior to rendering form (which clears session messages)
        $form = $this->DeletionForm($gridField);
        $hasMessage = $form && $form->getMessage();

        // Render modal
        $template = SSViewer::get_templates_by_class(__CLASS__, '_Modal');
        $viewer = new ArrayData([
            'ModalTitle' => $this->getModalTitle(),
            'ModalID' => $modalID,
            'ModalForm' => $form,
        ]);
        $modal = $viewer->renderWith($template)->forTemplate();

        // Build action button
        $button = new GridField_FormAction(
            $gridField,
            'deletionForm',
            "Delete {$this->getDummyObject()->plural_name()}",
            'deletionForm',
            null
        );
        $button
        ->addExtraClass('btn btn-outline-danger font-icon-trash btn--icon-large action_import')
        ->setForm($gridField->getForm())
        ->setAttribute('data-toggle', 'modal')
        ->setAttribute('aria-controls', $modalID)
        ->setAttribute('data-target', "#{$modalID}")
        ->setAttribute('data-modal', $modal);

        // If form has a message, trigger it to automatically open
        if ($hasMessage) {
            $button->setAttribute('data-state', 'open');
        }

        return [
            $this->targetFragment => $button->Field()
        ];
    }

    /**
     * delete is an action button
     *
     * @param GridField $gridField
     *
     * @return array
     */
    public function getActions($gridField)
    {
        return [
            'delete',
            'deletionForm',
        ];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        switch ($actionName) {
            case 'delete':
                return $this->handleDelete($data, $gridField);
            case 'deletionForm':
                return $this->DeletionForm($gridField);
            default:
                return null;
        }
    }

    /**
     * it is also a URL
     *
     * @param GridField $gridField
     *
     * @return array
     */
    public function getURLHandlers($gridField)
    {
        return [
            'delete' => 'handleDelete',
            'deletionForm' => 'DeletionForm',
        ];
    }

    /**
     * Generate a modal form for a single {@link DataObject} subclass.
     *
     * @param GridField $gridField
     * @return Form|false
     */
    public function DeletionForm($gridField = null)
    {
        if (!$gridField && !$gridField = $this->gridField) {
            return user_error('This button must be used in a gridfield.');
        }
        $this->gridField = $gridField;

        $dummyObj = $this->getDummyObject();

        if (!$dummyObj->canCreate(Security::getCurrentUser())) {
            return false;
        }

        $fields = $this->getPreparedFilterFields();

        $actions = new FieldList(
            FormAction::create('delete', "Delete {$dummyObj->plural_name()}")
            ->addExtraClass('btn btn-danger font-icon-trash')
        );

        $form = new Form(
            $gridField,
            'deletionForm',
            $fields,
            $actions
        );
        $form->setFormAction($gridField->Link('delete'));

        $this->extend('updateDeletionForm', $form);

        return $form;
    }

    /**
     * Deletes models from the gridfield list based on user-supplied filters.
     *
     * @param GridField $gridField
     * @param HTTPRequest $request
     * @return bool|HTTPResponse
     */
    public function handleDelete($gridField, HTTPRequest $request)
    {
        $data = $request->requestVars();
        $filters = array();

        // Prepare filters based on user input.
        foreach ($data as $key => $value) {
            // If this fields is a "filter by" field, and the value is truthy, add the filter.
            if (preg_match('/' . static::FILTER_BY_SUFFIX . '$/', $key) && $value) {
                $fieldName = str_replace(static::FILTER_BY_SUFFIX, '', $key);
                $filterType = $data[$fieldName . static::OPTION_FIELD_SUFFIX];
                if (empty($filterType)) {
                    $filterType = 'ExactMatch';
                }
                if (!empty($data[$fieldName . static::FILTER_INVERT_SUFFIX])) {
                    $filterType .= ':not';
                }
                $filters["$fieldName:$filterType"] = $data[$fieldName];
            }
        }
        // Ensure data objects are filtered to only include items in this gridfield.
        $filters['ID'] = $gridField->getManipulatedList()->column('ID');

        $deletions = $gridField->getModelClass()::get()->filter($filters);

        $message = '';
        if ($count = $deletions->count()) {
            //TODO delete them
            $message .= "Deleted {$count} records.";
        } else {
            $message .= 'Nothing to delete';
        }

        $gridField->getForm()->sessionMessage($message, 'good');
        return $gridField->redirectBack();
    }

    /**
     * Get the fields to display in the filter modal.
     * If {@link setFilterFields()} has not been called, this will be based on the class's getCMSFields
     * implementation or the default scaffolded fields for the class.
     *
     * @return FieldList
     */
    public function getFilterFields()
    {
        if (!$this->filterFields) {
            $obj = $this->getDummyObject();
            $fields = array_keys(DataObject::getSchema()->databaseFields($obj->ClassName));
            $fieldList = FieldList::create();
            // Get fields from object's CMSFields.
            foreach ($obj->getCMSFields()->flattenFields() as $field) {
                if (!in_array($field->Name, $fields)) {
                    continue;
                }
                $fieldList->add($field);
            }
            // Get scaffolded DB fields if getCMSFields has no DB Fields.
            if (!$fieldList->count()) {
                foreach ($obj->scaffoldFormFields() as $field) {
                    $fieldList->add($field);
                }
            }
            $this->filterFields = $fieldList;
        }
        return $this->filterFields;
    }

    /**
     * Set the fields to display in the filter modal.
     * Names of fields must match the names of database fields on the class which is held by the gridfield.
     *
     * @param array|FieldList $fields
     * @return $this
     */
    public function setFilterFields($fields)
    {
        if (is_array($fields)) {
            $fields = FieldList::create($fields);
        }
        if (!$fields instanceof FieldList) {
            throw new \BadMethodCallException('"fields" must be a FieldList or array.');
        }

        $this->filterFields = $fields;
        return $this;
    }

    /**
     * Get the options by which each field can be filtered.
     *
     * @return array
     */
    public function getFilterOptions()
    {
        return $this->filterOptions;
    }

    /**
     * Get the options by which each field can be filtered.
     *
     * The keys are names of database fields on the class which is held by the gridfield.
     * Values must be an array of search filter options.
     *
     * Note that if a given field is not set, this will fall back to the default options.
     * The key for the default options is {@link GridFieldDeleteRelationsButton::DEFAULT_OPTION}
     *
     * @param array $options
     * @return $this
     * @link https://docs.silverstripe.org/en/4/developer_guides/model/searchfilters/
     */
    public function setFilterOptions(array $options)
    {
        $this->filterOptions = array_merge($this->filterOptions, $options);
        return $this;
    }

    /**
     * Get the title of the filter modal.
     *
     * @return string
     */
    public function getModalTitle()
    {
        if (!$this->modalTitle) {
            $this->modalTitle = "Delete {$this->getDummyObject()->plural_name()}";
        }
        return $this->modalTitle;
    }

    /**
     * Set the title of the filter modal.
     *
     * @param string $modalTitle
     * @return $this
     */
    public function setModalTitle($modalTitle)
    {
        $this->modalTitle = $modalTitle;
        return $this;
    }

    /**
     * Get all composite fields for the modal form.
     *
     * @return FieldList
     */
    protected function getPreparedFilterFields()
    {
        $fields = FieldList::create();
        foreach ($this->getFilterFields() as $field) {
            $fields->add($this->getFieldAsComposite($field));
        }
        return $fields;
    }

    /**
     * Get a CompositeField for the given field which contains all
     * necessary filter fields to support the given field.
     *
     * @param FormField $field
     * @return \SilverStripe\Forms\CompositeField
     */
    protected function getFieldAsComposite(FormField $field)
    {
        $group = FieldGroup::create(
            "'{$field->Title()}' filter group",
            [
                $filterBy = CheckboxField::create(
                    $field->Name . static::FILTER_BY_SUFFIX,
                    "Filter by {$field->Title()}?"
                ),
                $field,
                $options = $this->getFilterOptionsField($field->Name),
                $invert = CheckboxField::create(
                    $field->Name . static::FILTER_INVERT_SUFFIX,
                    'Invert Filter?'
                ),
            ]
        );
        if (ModuleLoader::inst()->getManifest()->moduleExists('unclecheese/display-logic')) {
            $field->displayIf($filterBy->Name)->isChecked();
            $options->displayIf($filterBy->Name)->isChecked();
            $invert->displayIf($filterBy->Name)->isChecked();
        }
        return $group;
    }

    /**
     * Get a DropdownField with filter options as defined in
     * {@link GridFieldDeleteRelationsButton::setFilterOptions()}.
     *
     * @param string $fieldName
     * @return DropdownField
     */
    protected function getFilterOptionsField($fieldName)
    {
        $allOptions = $this->filterOptions;
        if (array_key_exists($fieldName, $allOptions)) {
            $options = $allOptions[$fieldName];
        } else {
            $options = $allOptions[static::DEFAULT_OPTION];
        }
        $field = DropdownField::create(
            $fieldName . static::OPTION_FIELD_SUFFIX,
            "$fieldName Filter Type",
            array_combine($options, $options)
        );
        if (in_array('ExactMatch', $options)) {
            $field->setValue('ExactMatch');
        } else {
            $field->setHasEmptyDefault(true);
        }
        $this->extend('updateFilterOptionsField', $field, $fieldName);
        return $field;
    }

    /**
     * Returns a singleton of the class held by the gridfield.
     *
     * @return \SilverStripe\ORM\DataObject
     */
    protected function getDummyObject()
    {
        if (!$this->dummyObject && $this->gridField) {
            $this->dummyObject = $this->gridField->getModelClass()::singleton();
        }
        return $this->dummyObject;
    }
}
