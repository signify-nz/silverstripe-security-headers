<?php

namespace Signify\SecurityHeaders\Models;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\DataObject;

/**
 * A single allowed CSP source value, linked to one or more CSPDirective records.
 */
class CSPPolicy extends DataObject
{
    private static $singular_name = 'CSP Policy';

    private static $plural_name = 'CSP Policies';

    private static $table_name = 'Signify_CSPPolicy';

    private static $db = [
        'Value' => 'Varchar(255)',
        'Description' => 'Varchar(255)',
    ];

    private static $many_many = [
        'Directive' => CSPDirective::class,
    ];

    private static $summary_fields = [
        'Value' => 'Value',
        'Description' => 'Description',
    ];

    /**
     * {@inheritDoc}
     */
    public function getCMSCompositeValidator(): CompositeValidator
    {
        $validator = parent::getCMSCompositeValidator();

        $validator->addValidator(RequiredFieldsValidator::create(['Value']));

        return $validator;
    }

    /**
     * Returns Value, or the record ID if Value isn't set yet.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->Value ?: 'Policy #' . $this->ID;
    }

    /**
     * Validates Value has no whitespace, semicolons, commas, or quotes.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        $value = $this->Value;

        if (trim($value) === '') {
            $result->addFieldError('Value', 'Value cannot be empty.');
            return $result;
        }

        if (preg_match('/\s/', $value)) {
            $result->addFieldError('Value', 'Value cannot contain any whitespace.');
        }

        if (strpos($value, ';') !== false) {
            $result->addFieldError('Value', 'Value cannot contain a semicolon.');
        }

        if (strpos($value, ',') !== false) {
            $result->addFieldError('Value', 'Value cannot contain a comma. Use a separate CSPPolicy record for each source.');
        }

        if (strpos($value, "'") !== false) {
            $result->addFieldError('Value', 'Value should not contain quotes. Quoted keywords are handled separately.');
        }

        return $result;
    }
}
