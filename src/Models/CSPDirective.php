<?php

namespace Signify\SecurityHeaders\Models;

use Signify\SecurityHeaders\Models\CSPPolicy;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Forms\LiteralField;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * A single CSP directive (e.g. script-src) and its allowed keywords/sources.
 */
class CSPDirective extends DataObject
{
    private static $singular_name = 'CSP Directive';

    private static $plural_name = 'CSP Directives';

    private static $table_name = 'Signify_CSPDirective';

    private static $db = [
        'Name' => 'Enum("default-src,script-src,style-src,'
            . 'img-src,font-src,frame-src,connect-src,'
            . 'object-src,base-uri,form-action,frame-ancestors")',
        'AllowSelf' => 'Boolean',
        'AllowUnsafeInline' => 'Boolean',
        'AllowUnsafeEval' => 'Boolean',
        'AllowDataUri' => 'Boolean',
        'AllowNone' => 'Boolean',
    ];

    private static $belongs_many_many = [
        'Policies' => CSPPolicy::class
    ];

    /**
     * {@inheritDoc}
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $allOptions = $this->dbObject('Name')->enumValues();

        $usedNames = CSPDirective::get()
            ->exclude('ID', $this->ID)
            ->column('Name');

        $availableOptions = array_diff($allOptions, $usedNames);

        if ($this->Name && !in_array($this->Name, $availableOptions)) {
            $availableOptions[] = $this->Name;
        }

        $fields->replaceField('Name', DropdownField::create(
            'Name',
            'Directive Name',
            array_combine($availableOptions, $availableOptions)
        )->setEmptyString('Select a directive'));

        $fields->dataFieldByName('AllowSelf')
            ->setDescription("Adds 'self' - allows resources from this site's own origin.");

        $fields->dataFieldByName('AllowUnsafeInline')
            ->setDescription("Adds 'unsafe-inline' - allows inline scripts/styles. Weakens XSS protection.");

        $fields->dataFieldByName('AllowUnsafeEval')
            ->setDescription("Adds 'unsafe-eval' - allows eval() and similar. Weakens XSS protection.");

        $fields->dataFieldByName('AllowDataUri')
            ->setDescription("Adds 'data:' - allows base64-encoded inline resources.");

        $fields->dataFieldByName('AllowNone')
            ->setDescription("Adds 'none' - blocks this directive entirely. Cannot combine with other options.");

        // Only relevant to script-src / style-src
        $fields->dataFieldByName('AllowUnsafeInline')
            ->displayIf('Name')->isEqualTo('script-src')
            ->orIf('Name')->isEqualTo('style-src')
            ->end();

        // Only relevant to script-src
        $fields->dataFieldByName('AllowUnsafeEval')
            ->displayIf('Name')->isEqualTo('script-src')
            ->end();

        // Only relevant to img-src / font-src / script-src / style-src
        $fields->dataFieldByName('AllowDataUri')
            ->displayIf('Name')->isEqualTo('img-src')
            ->orIf('Name')->isEqualTo('font-src')
            ->orIf('Name')->isEqualTo('script-src')
            ->orIf('Name')->isEqualTo('style-src')
            ->end();

        $recommendations = [
            'default-src' => "Recommended: Allow self.",
            'script-src' => "Recommended: Allow self, Allow unsafe inline, Allow unsafe eval.",
            'style-src' => "Recommended: Allow self, Allow unsafe inline.",
            'img-src' => "Recommended: Allow self, Allow data URI.",
            'object-src' => "Recommended: Allow none.",
            'base-uri' => "Recommended: Allow self.",
            'form-action' => "Recommended: Allow self.",
            'frame-ancestors' => "Recommended: Allow self.",
        ];

        foreach ($recommendations as $directiveName => $hintText) {
            $hintField = Wrapper::create(
                LiteralField::create(
                    'Hint_' . $directiveName,
                    '<p class="form__field-description">' . $hintText . '</p>'
                )
            );

            $hintField->displayIf('Name')->isEqualTo($directiveName)->end();

            $fields->insertAfter('Name', $hintField);
        }


        // Customize the Policies GridField's "add existing" search
        $policiesField = $fields->fieldByName('Root.Policies.Policies');

        if ($policiesField) {
            $config = GridFieldConfig_RelationEditor::create();

            /** @var GridFieldAddExistingAutocompleter $autocompleter */
            $autocompleter = $config->getComponentByType(GridFieldAddExistingAutocompleter::class);
            $autocompleter->setSearchFields(['Description', 'Value']);

            $policiesField->setConfig($config);
        }

        return $fields;
    }

    /**
     * {@inheritDoc}
     */
    public function getCMSCompositeValidator(): CompositeValidator
    {
        $validator = parent::getCMSCompositeValidator();

        $validator->addValidator(RequiredFieldsValidator::create(['Name']));

        return $validator;
    }

    /**
     * Validates Name is set and unique, and that AllowNone isn't combined
     * with AllowSelf, other source options, or linked policies.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (trim((string) $this->Name) === '') {
            $result->addFieldError('Name', 'Name is required.');
            return $result;
        }

        $duplicateExists = CSPDirective::get()
            ->filter('Name', $this->Name)
            ->exclude('ID', $this->ID)
            ->exists();

        if ($duplicateExists) {
            $result->addFieldError('Name', 'A directive with this name already exists.');
        }

        if ($this->AllowNone) {
            if ($this->AllowSelf) {
                $result->addFieldError('AllowNone', "Cannot combine 'None' with 'Allow self'.");
            }

            if ($this->AllowUnsafeInline || $this->AllowUnsafeEval || $this->AllowDataUri) {
                $result->addFieldError('AllowNone', "Cannot combine 'None' with other source options.");
            }

            if ($this->isInDB() && $this->Policies()->exists()) {
                $result->addFieldError(
                    'AllowNone',
                    "Cannot combine 'None' with linked policies, remove all policies first."
                );
            }
        }

        return $result;
    }
}
