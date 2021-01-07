<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Auth;
use App\Helpers\Obfuscate;
use App\Models\Criteria;
use App\Models\CriteriaOption;
use App\Models\Scholarship;

class ScholarshipController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
    }

    // -------------------------------------------------------------------------

    public function fetch(Request $request, $id)
    {
        $id_int = Scholarship::hash()->decode($id);

        $scholarship = Scholarship::findOrFail($id_int);

        $criteria = DB::connection('sql-app')
            ->table('Scholarship_Criteria_Option')
            ->select([
                DB::raw('Criteria.id AS criteria_id'),
                DB::raw('Criteria_Option.id AS criteria_option_id'),
                DB::raw('Criteria.name AS criteria_name'),
                DB::raw('Criteria_Option.name AS criteria_option_name'),
                DB::raw('Criteria_Option.description AS criteria_option_description'),
                DB::raw('Scholarship_Criteria_Option.is_required')
            ])
            ->leftJoin('Criteria_Option', 'Criteria_Option.id', 'Scholarship_Criteria_Option.option_id')
            ->leftJoin('Criteria', 'Criteria.id', '=', 'Criteria_Option.criteria_id')
            ->where('scholarship_id', '=', $id_int)
            ->whereNull('Scholarship_Criteria_Option.deleted_at')
            ->whereNull('Criteria_Option.deleted_at')
            ->whereNull('Criteria.deleted_at')
            ->get();

        $assembled = [];

        // Assemble Base Array, Keyed by Criteria ID
        foreach ($criteria as &$criterion) {
            $assembled[Criteria::hash()->encode((int)$criterion->criteria_id)] = [
                'name'    =>  $criterion->criteria_name,
                'options' =>  []
            ];
        }

        // Add Criteria Options to Criterias
        foreach ($criteria as &$criterion) {
            array_push($assembled[Criteria::hash()->encode((int)$criterion->criteria_id)]['options'], [
                'id'          =>  CriteriaOption::hash()->encode($criterion->criteria_option_id),
                'name'        =>  $criterion->criteria_option_name,
                'description' =>  $criterion->criteria_option_description,
                'is_required' =>  $criterion->is_required
            ]);
        }

        $scholarship = $scholarship->toArray();

        $scholarship['id'] = $id;
        $scholarship['criteria'] = $assembled;

        return response()->json($scholarship, 200, [], JSON_NUMERIC_CHECK);
    }

    //Add Scholarship Register
    public function scholarshipRegister(Request $request)
    {
        $connection = DB::connection('sql-app')->table('scholarship_register');
        $userId = Auth::id();
        $scholarships = $connection->where('user_id', $userId)->first();
        $scholarshipArr = [
            'user_id'                      =>  $userId,
            'age'                          =>  $request->input('age'),
            'gender'                       =>  $request->input('gender'),
            'gpa'                          =>  $request->input('gpa'),
            'enrollment_level'             =>  $request->input('enrollment_level'),
            'race'                         =>  $request->input('race'),
            'religion'                     =>  $request->input('religion'),
            'major'                        =>  $request->input('major'),
            'state'                        =>  $request->input('state'),
            'country'                      =>  $request->input('country'),
            'act'                          =>  $request->input('act'),
            'sat'                          =>  $request->input('sat'),
            'career_interest'              =>  $request->input('career_interest'),
            'corp_affiliation'             =>  $request->input('corp_affiliation'),
            'club'                         =>  $request->input('club'),
            'military_affiliation'         =>  $request->input('military_affiliation'),
            'sport'                        =>  $request->input('sport'),
            'organization'                 =>  $request->input('organization')
        ];

        if ($scholarships == NULL) {
            $connection->insert($scholarshipArr);
        } else {
            $connection->where('user_id', $userId)->update($scholarshipArr);
        }

        return response()->json(['result' => 'success'], 200, [], JSON_NUMERIC_CHECK);
    }

    //Get Scholarship Register By User Id
    public function getScholarshipRegister($userId)
    {
        $scholarships = DB::connection('sql-app')
            ->table('scholarship_register')
            ->where('user_id', $userId)
            ->first();

        if ($scholarships != NULL) {
            $response['result'] = "user found";
        } else {
            $response['result'] = "user not found";
        }
        $response['data'] = $scholarships;
        return response()->json($response, 200, [], JSON_NUMERIC_CHECK);
    }
}