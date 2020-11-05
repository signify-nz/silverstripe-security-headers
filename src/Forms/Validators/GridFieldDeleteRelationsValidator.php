<?php

namespace Signify\Forms\Validators;

use Signify\Forms\GridField\GridFieldDeleteRelationsButton;
use SilverStripe\Forms\Validator;

class GridFieldDeleteRelationsValidator extends Validator
{
    public function php($data)
    {
        $valid = true;
        $filters = array();
        foreach ($data as $key => $value) {
            // If this fields is a "filter by" field, and the value is truthy, add the filter.
            if (preg_match('/' . GridFieldDeleteRelationsButton::FILTER_BY_SUFFIX . '$/', $key) && $value) {
                $filters[] = $key;
            }
        }

        if (!empty($filters) && !empty($data[GridFieldDeleteRelationsButton::DELETE_ALL])) {
            $message = "A filter checkbox and 'Delete all' cannot be checked simultaneously.";
            $filters[] = GridFieldDeleteRelationsButton::DELETE_ALL;
            foreach ($filters as $fieldName) {
                $this->validationError($fieldName, $message);
            }
            $valid = false;
        }

        if (empty($filters) && empty($data[GridFieldDeleteRelationsButton::DELETE_ALL])) {
            $message = "At least one filter checkbox or 'Delete all' must be checked.";
            $this->validationError(GridFieldDeleteRelationsButton::DELETE_ALL, $message);
            $valid = false;
        }

        if (!$valid) {
            $this->validationError('', 'Please correct the validation errors.');
        }

        return $valid;
    }
}
