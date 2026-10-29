<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Swedish messages for the validation rules the application uses. Rules
    | missing here fall back to the English messages.
    |
    */

    'accepted' => ':Attribute måste godkännas.',
    'array' => ':Attribute måste vara en lista.',
    'boolean' => ':Attribute måste vara sant eller falskt.',
    'date' => ':Attribute måste vara ett giltigt datum.',
    'email' => ':Attribute måste vara en giltig e-postadress.',
    'enum' => 'Det valda värdet för :attribute är ogiltigt.',
    'image' => ':Attribute måste vara en bild.',
    'in' => 'Det valda värdet för :attribute är ogiltigt.',
    'integer' => ':Attribute måste vara ett heltal.',
    'max' => [
        'array' => ':Attribute får inte ha fler än :max val.',
        'file' => ':Attribute får inte vara större än :max kilobyte.',
        'numeric' => ':Attribute får inte vara större än :max.',
        'string' => ':Attribute får inte vara längre än :max tecken.',
    ],
    'min' => [
        'array' => ':Attribute måste ha minst :min val.',
        'file' => ':Attribute måste vara minst :min kilobyte.',
        'numeric' => ':Attribute måste vara minst :min.',
        'string' => ':Attribute måste vara minst :min tecken.',
    ],
    'numeric' => ':Attribute måste vara ett nummer.',
    'required' => ':Attribute måste fyllas i.',
    'string' => ':Attribute måste vara text.',
    'unique' => ':Attribute används redan.',

    'attributes' => [
        'email' => 'e-post',
        'locale' => 'språk',
        'name' => 'namn',
        'role' => 'roll',
    ],

];
