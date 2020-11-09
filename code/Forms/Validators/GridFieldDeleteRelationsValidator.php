<?php

class GridFieldDeleteRelationsValidator extends Validator
{
    public function php($data)
    {
        $valid = true;
        $filters = array();
        // Check for checked filter checkboxes.
        foreach ($data as $key => $value) {
            if (preg_match('/' . GridFieldDeleteRelationsButton::FILTER_BY_SUFFIX . '$/', $key) && $value) {
                $filters[] = $key;
            }
        }

        // If the delete all checkbox is checked, no other filters can be checked.
        if (!empty($filters) && !empty($data[GridFieldDeleteRelationsButton::DELETE_ALL])) {
            $message = _t(
                GridFieldDeleteRelationsButton::class . '.VALIDATION_TooManyFilters',
                'A filter checkbox and "Delete all" cannot be checked simultaneously.'
            );
            $filters[] = GridFieldDeleteRelationsButton::DELETE_ALL;
            foreach ($filters as $fieldName) {
                $this->validationError($fieldName, $message, 'required');
            }
            $valid = false;
        }

        // At least one checkbox must be checked.
        if (empty($filters) && empty($data[GridFieldDeleteRelationsButton::DELETE_ALL])) {
            $message = _t(
                GridFieldDeleteRelationsButton::class . '.VALIDATION_RequireFilters',
                'At least one filter checkbox or "Delete all" must be checked.'
            );
            $this->validationError(GridFieldDeleteRelationsButton::DELETE_ALL, $message, 'required');
            $valid = false;
        }

        // Add a message to the form itself.
        if (!$valid) {
            $this->form->sessionMessage(_t(
                GridFieldDeleteRelationsButton::class . '.VALIDATION_FormMessage',
                'Please correct the validation errors.'
            ), 'bad');
        }

        return $valid;
    }
}
