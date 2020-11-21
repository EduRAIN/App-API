<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Auth;
use DB;
use Validator;

use App\Helpers\Obfuscate;

use App\Models\Fafsa;

class FafsaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // -------------------------------------------------------------------------

    public function fetch(Request $request)
    {
        $fafsa = Fafsa::where('user_id', '=', Auth::id())
                      ->get()
                      ->makeVisible('id')
                      ->toArray();

        foreach ($fafsa as &$f)
        {
            $f['id'] = Fafsa::hash()->encode($f['id']);
        }

        return response()->json($fafsa, 200, [], JSON_NUMERIC_CHECK);
    }

    // -------------------------------------------------------------------------

    public function create(Request $request)
    {
        if (($v = Validator::make($request->all(), Fafsa::validations())) && $v->fails())
        {
            return response()->json([
                'status'    =>  'failure',
                'error'     =>  'data_validation_failed',
                'fields'    =>  $v->errors()
            ], 400);
        }

        try
        {
            $fafsa = Fafsa::where('user_id', '=', Auth::id())
                          ->where('academic_year', '=', $request->input('academic_year'))
                          ->first();
        }

        catch (\Exception $e)
        {
            // Do Nothing
        }

        if ($fafsa)
        {
            return response()->json([
                'status'    =>  'failure',
                'error'     =>  'fafsa_already_exists'
            ], 400);
        }

        $fafsa = Fafsa::query()
                      ->create([
                          'user_id'       =>  Auth::id(),
                          'academic_year' =>  $request->input('academic_year')
                      ]);

        return response()->json([
            'result'  => 'success',
            'id'      =>  Fafsa::hash()->encode($fafsa->id)
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
