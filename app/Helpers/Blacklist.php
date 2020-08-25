<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class Blacklist
{
    public function __construct()
    {
        //
    }

    /**
     * Return status of blacklist prescence for a given IP address.
     *
     * @return Boolean
     */
    public static function check($ip)
    {
        return (bool)DB::connection('sql-cs')
                       ->table('Blacklist')
                       ->where('ip_bin', '=', DB::raw('INET6_ATON("' . $ip . '")'))
                       ->limit(1)
                       ->get()
                       ->count();
    }
}
