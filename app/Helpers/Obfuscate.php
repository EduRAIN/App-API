<?php
namespace App\Helpers;

class Obfuscate
{
    public function __construct()
    {
        //
    }

    public static function fromInt($hashid, $values)
    {
        if (is_int($values))
        {
            return $hashid->encode($values);
        }

        $values = json_decode(json_encode($values), true);

        return array_map(function ($item) use ($hashid) {
            $item['id'] = $hashid->encode($item['id']);
            return $item;
        }, $values);
    }

    public static function toInt($hashid, $values)
    {
        if (is_string($values))
        {
            return $hashid->decode($values);
        }

        return array_map(function ($item) use ($hashid) {
            $item['id'] = $hashid->decode($item['id']);
            return $item;
        }, $values);
    }
}
