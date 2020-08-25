<?php
namespace App\Helpers;

class Nullable
{
    public function __construct()
    {
        //
    }

    public static function generate($validations)
    {
        $fields = [];

        foreach ($validations as $key => $rules)
        {
            if ((gettype($rules) == 'string' && strpos($rules, 'nullable') !== false) ||
                (gettype($rules) == 'array' && array_search('nullable', $rules) !== false))
            {
                $fields[$key] = null;
            }
        }

        return $fields;
    }
}
