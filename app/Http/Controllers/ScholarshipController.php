<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;

use App\Helpers\Obfuscate;

use App\Models\Criteria;
use App\Models\CriteriaOption;
use App\Models\Scholarship;
use App\Models\ScholarshipApplication;

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

    public function find_favourite_scholarship(Request $request)
    {
        $user_id = ScholarshipApplication::hash()->decode($request->user_id);
        $scholarship_id = ScholarshipApplication::hash()->decode($request->scholarship_id);
        $criteria = DB::connection('sql-app')
            ->table('Scholarship_Application')
            ->where('user_id', '=', $user_id)
            ->where('scholarship_id', '=', $scholarship_id)
            ->whereNull('Scholarship_Application.deleted_at')
            ->whereNull('Scholarship_Application.deleted_at')
            ->whereNull('Scholarship_Application.deleted_at')
            ->get()
            ->first();

        if (!empty($criteria)) {
            $favouriteUser = ScholarshipApplication::findOrFail($criteria->id);
            $favouriteUser->is_favorite = 1;
            $favouriteUser->save();

            $favouriteScholarshipStatus['id'] = $criteria->id;
            $favouriteScholarshipStatus['status'] = 1;
            $favouriteScholarshipStatus['msg'] = "Successfully scholarship add to user favorite list";
        } else {
            $favouriteScholarshipStatus['status'] = 0;
            $favouriteScholarshipStatus['msg'] = "Not found";
        }

        return response()->json($favouriteScholarshipStatus, 200, [], JSON_NUMERIC_CHECK);
    }
}