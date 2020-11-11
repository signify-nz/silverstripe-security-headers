<?php

namespace Signify\Forms\GridField;

use Signify\Forms\Validators\GridFieldDeleteRelationsValidator;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
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
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\Requirements;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Adds an delete button to the bottom or top of a GridField.
 * Clicking the button opens a modal in which a user can select filter options.
 * The user can then delete models from the gridfield's list based on those filter options.
 */
class GridFieldDeleteRelationsButton implements GridField_HTMLProvider, GridField_URLHandler
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
            'ExactMatch',
            'PartialMatch',
            'LessThan',
            'LessThanOrEqual',
            'GreaterThan',
            'GreaterThanOrEqual',
            'StartsWith',
            'EndsWith',
        ]
    ];

    const DEFAULT_OPTION = '__default';

    const OPTION_FIELD_SUFFIX = '__FilterOption';

    const FILTER_BY_SUFFIX = '__FilterBy';

    const FILTER_INVERT_SUFFIX = '__FilterInvert';

    const DELETE_ALL = 'DeleteAll__FilterAll';

    /**
     * Filter options which are commonly used with string values.
     * @var array
     */
    const STRING_FILTER_OPTIONS = [
        'ExactMatch',
        'PartialMatch',
        'StartsWith',
        'EndsWith',
    ];

    /**
     * Filter options which are commonly used with numbers or date values.
     * @var array
     */
    const NUMBER_DATE_FILTER_OPTIONS = [
        'ExactMatch',
        'LessThan',
        'LessThanOrEqual',
        'GreaterThan',
        'GreaterThanOrEqual',
    ];

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
        if (ModuleLoader::inst()->getManifest()->moduleExists('unclecheese/display-logic')) {
            Requirements::javascript('signify-nz/silverstripe-security-headers:client/dist/main.js');
        }
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
            FormAction::create('delete', _t(
                self::class . '.DELETE',
                'Delete {pluralName}',
                ['pluralName' => $dummyObj->plural_name()]
            ))
            ->addExtraClass('btn btn-danger font-icon-trash')
        );

        $form = new Form(
            $gridField,
            'deletionForm',
            $fields,
            $actions,
            new GridFieldDeleteRelationsValidator()
        );
        $form->setFormAction($gridField->Link('delete'));
        if ($form->getMessage()) {
            $form->addExtraClass('validationerror');
        }

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
        $data = $this->parseQueryString($request->getBody());
        if (empty($data)) {
            $data = $request->requestVars();
        }
        $form = $this->DeletionForm($gridField);
        $form->loadDataFrom($data);
        $validationResult = $form->validationResult();
        if (!$validationResult->isValid()) {
            $form->setSessionValidationResult($validationResult);
            $form->setSessionData($data);
            return $gridField->redirectBack();
        }

        // Prepare filters based on user input.
        $filters = array();
        foreach ($data as $key => $value) {
            // If this fields is a "filter by" field, and the value is truthy, add the filter.
            if (preg_match('/' . self::FILTER_BY_SUFFIX . '$/', $key) && $value) {
                $fieldName = str_replace(self::FILTER_BY_SUFFIX, '', $key);
                $filterType = $data[$fieldName . self::OPTION_FIELD_SUFFIX];
                if (empty($filterType)) {
                    $filterType = 'ExactMatch';
                }
                if (!empty($data[$fieldName . self::FILTER_INVERT_SUFFIX])) {
                    $filterType .= ':not';
                }
                $filters["$fieldName:$filterType"] = empty($data[$fieldName]) ? null : $data[$fieldName];
            }
        }

        // Ensure data objects are filtered to only include items in this gridfield.
        $filters['ID'] = $gridField->getManipulatedList()->column('ID');
        if (empty($filters['ID'])) {
            $deletions = new ArrayList();
        } else {
            $deletions = $gridField->getModelClass()::get()->filter($filters);
        }

        $message = '';
        if ($count = $deletions->count()) {
            /* @var $dataObject DataObject */
            foreach ($deletions as $dataObject) {
                $dataObject->delete();
                $dataObject->destroy();
            }
            $message .= _t(self::class . '.DELETED', 'Deleted one record.|Deleted {count} records.', ['count' => $count]);
        } else {
            $message .= _t(self::class . '.NOT_DELETED', 'Nothing to delete.');
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
            $this->modalTitle = _t(
                self::class . '.DELETE',
                'Delete {pluralName}',
                ['pluralName' => $this->getDummyObject()->plural_name()]
            );
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
        $fields->add(CheckboxField::create(
            self::DELETE_ALL,
            _t(
                self::class . '.DELETE_ALL',
                'Delete all {pluralName}',
                ['pluralName' => $this->getDummyObject()->plural_name()]
            )
        ));
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
        $fields = [
            $filterBy = CheckboxField::create(
                $field->Name . self::FILTER_BY_SUFFIX,
                _t(
                    self::class . '.FILTER_BY',
                    'Filter by "{fieldName}"',
                    ['fieldName' => $field->Title()]
                ),
            ),
            $field,
            $options = $this->getFilterTypesField($field->Name),
            $invert = CheckboxField::create(
                $field->Name . self::FILTER_INVERT_SUFFIX,
                _t(
                    self::class . '.FILTER_INVERT',
                    'Invert Filter'
                ),
            )
        ];

        $group = FieldGroup::create(
            _t(
                self::class . '.FILTER_GROUP',
                '"{fieldName}" filter group',
                ['fieldName' => $field->Title()]
            ),
            $fields
        );
        if (ModuleLoader::inst()->getManifest()->moduleExists('unclecheese/display-logic')) {
            $group = Wrapper::create($group);
            $field->displayIf($filterBy->Name)->isChecked();
            $options->displayIf($filterBy->Name)->isChecked();
            $invert->displayIf($filterBy->Name)->isChecked();
            $group->hideIf(self::DELETE_ALL)->isChecked();
        }

        return $group;
    }

    /**
     * Get a DropdownField with filter types as defined in
     * {@link GridFieldDeleteRelationsButton::setFilterOptions()}.
     *
     * @param string $fieldName
     * @return FormField
     */
    protected function getFilterTypesField($fieldName)
    {
        $allOptions = $this->filterOptions;
        if (array_key_exists($fieldName, $allOptions)) {
            $options = $allOptions[$fieldName];
        } else {
            $options = $allOptions[self::DEFAULT_OPTION];
        }
        $filterFieldName = $fieldName . self::OPTION_FIELD_SUFFIX;
        $filterFieldTitle = _t(
            self::class . '.FILTER_TYPE',
            '"{fieldName}" Filter Type',
            ['fieldName' => $fieldName]
        );
        if (count($options) == 1) {
            $field = ReadonlyField::create(
                $filterFieldName,
                $filterFieldTitle,
                $options[0]
            )->setIncludeHiddenField(true)
            ->setTemplate('Signify\Forms\ReadonlyField');
        } else {
            $field = DropdownField::create(
                $filterFieldName,
                $filterFieldTitle,
                array_combine($options, $options)
            );
            $field->setHasEmptyDefault(true);
            if (in_array('ExactMatch', $options)) {
                $field->setValue('ExactMatch');
            }
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

    /**
     * An alternative to {@link parse_str()} which keeps periods intact.
     * This allows using dot syntax for filtering by relationships.
     *
     * @param string $data
     * @return array
     */
    protected function parseQueryString($data)
    {
        if (empty($data)) {
            return array();
        }
        $data = urldecode($data);

        $data = preg_replace_callback('/(?:^|(?<=&))[^=[]+/', function($match) {
            return bin2hex(urldecode($match[0]));
        }, $data);

        parse_str($data, $result);

        return array_combine(array_map('hex2bin', array_keys($result)), $result);
    }
}
