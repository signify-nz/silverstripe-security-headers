<?php

namespace SilverStripe\Forms\GridField;

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

/**
 * Adds an "Print" button to the bottom or top of a GridField.
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
        $template = SSViewer::get_templates_by_class(GridFieldImportButton::class, '_Modal');
        $viewer = new ArrayData([
            'ImportModalTitle' => $this->getModalTitle(),
            'ImportModalID' => $modalID,
            'ImportIframe' => false,
            'ImportForm' => $form,
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
        ->addExtraClass('btn btn-secondary font-icon-trash btn--icon-large')
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
     * @param string $modalTitle
     * @return $this
     */
    public function setModalTitle($modalTitle)
    {
        $this->modalTitle = $modalTitle;
        return $this;
    }

    /**
     * Generate a CSV import form for a single {@link DataObject} subclass.
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

        $fields = new FieldList(
            // TODO: ADD FILTER FIELDS
            // decide whether to do this dynamically just based on the model, or if these should be passed in (probably the latter)
        );

        $actions = new FieldList(
            FormAction::create('delete', "Delete {$dummyObj->plural_name()}")
            ->addExtraClass('btn btn-outline-secondary font-icon-trash')
        );

        $form = new Form(
            $gridField,
            "deletionForm",
            $fields,
            $actions
        );
        $form->setFormAction($gridField->Link('delete'));

        $this->extend('updateDeletionForm', $form);

        return $form;
    }

    /**
     * Imports the submitted CSV file based on specifications given in
     * {@link self::model_importers}.
     * Redirects back with a success/failure message.
     *
     * @todo Figure out ajax submission of files via jQuery.form plugin
     *
     * @param GridField $gridField
     * @param HTTPRequest $request
     * @return bool|HTTPResponse
     */
    public function handleDelete($gridField, HTTPRequest $request)
    {
        $data = $request->requestVars();
        // TODO get the records which need to be deleted.

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
