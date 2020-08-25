<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;

use App\Helpers\Obfuscate;

use App\Models\Fafsa;
use App\Models\FafsaAnswer;
use App\Models\FafsaQuestion;
use App\Models\FafsaResponse;

class FafsaResponseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // -------------------------------------------------------------------------

    public function update(Request $request, $id)
    {
        $fafsa = Fafsa::findOrFail(Fafsa::hash()->decode($id));

        $sync = [];
        $safe = [];

        // Iterate Responses with Questions as Key
        foreach ($request->input('responses') as $question => $responses)
        {
            $question = FafsaQuestion::where('name', 'LIKE', $question)->firstOrFail();

            // Convert Single Responses to Array for Iteration
            if (!is_array($responses))
            {
                $responses = [$responses];
            }

            // Determine Expected Response Type
            $type = [ 1 => 'boolean', 2 => 'select', 3 => 'multi', 4 => 'numeric', 5 => 'text', 6 => 'date' ][$question->type];

            foreach ($responses as $response)
            {
                if (($type == 'boolean' && !is_bool($response)) ||
                    ($type == 'numeric' && !is_int($response)) ||
                    ($type == 'date' && !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[0-9]|[1-2][0-9]|3[0-1])$/', $response)) ||
                    (($type == 'select' || $type == 'multi' || $type == 'text') && !is_string($response)))
                {
                    return response()->json(['result' => 'error', 'error' => 'invalid_data_type'], 400, [], JSON_NUMERIC_CHECK);
                }

                if ($type == 'boolean')
                {
                    $sync[$question->id] = [
                        'fafsa_answer_id'   =>  null,
                        'data_boolean'      =>  $response,
                        'data_numeric'      =>  null,
                        'data_text'         =>  null,
                        'data_date'         =>  null
                    ];
                }

                else if ($type == 'date')
                {
                    $sync[$question->id] = [
                        'fafsa_answer_id'   =>  null,
                        'data_boolean'      =>  null,
                        'data_numeric'      =>  null,
                        'data_text'         =>  null,
                        'data_date'         =>  $response
                    ];
                }

                else if ($type == 'select' || $type == 'multi')
                {
                    $answer = FafsaAnswer::where('fafsa_question_id', '=', $question->id)
                                         ->where('name', 'LIKE', $response)
                                         ->first();

                    // Check for Answer Already Attached
                    if (FafsaResponse::where('fafsa_id', '=', $fafsa->id)
                                     ->where('fafsa_answer_id', '=', $answer->id)
                                     ->whereNull('deleted_at')
                                     ->count() == 0)
                    {
                        // Attach New Responses
                        $fafsa->responses()->attach([$question->id => [
                            'fafsa_answer_id'   =>  $answer->id,
                            'data_boolean'      =>  null,
                            'data_numeric'      =>  null,
                            'data_text'         =>  null,
                            'data_date'         =>  null
                        ]]);
                    }

                    // Check that Question Exists in Cache
                    if (!isset($safe[$question->id]))
                    {
                        $safe[$question->id] = [];
                    }

                    // Add Answer to List of Deletion Exclusions
                    array_push($safe[$question->id], $answer->id);
                }

                else if ($type == 'text')
                {
                    $sync[$question->id] = [
                        'fafsa_answer_id'   =>  null,
                        'data_boolean'      =>  null,
                        'data_numeric'      =>  null,
                        'data_text'         =>  $response,
                        'data_date'         =>  null
                    ];
                }

                else if ($type == 'numeric')
                {
                    $sync[$question->id] = [
                        'fafsa_answer_id'   =>  null,
                        'data_boolean'      =>  null,
                        'data_numeric'      =>  $response,
                        'data_text'         =>  null,
                        'data_date'         =>  null
                    ];
                }
            }
        }

        // Attach/Detach Single Responses
        if (count($sync))
        {
            $fafsa->responses()->syncWithoutDetaching($sync);
        }

        // Remove Unselected Multi-Select Options
        if (count($safe))
        {
            foreach ($safe as $question => $values)
            {
                FafsaResponse::query()
                             ->where('fafsa_question_id', '=', $question)
                             ->whereNotIn('fafsa_answer_id', $values)
                             ->whereNull('deleted_at')
                             ->update([
                                 'deleted_at' => date('c')
                               ]);
            }
        }

        return response()->json(['result' => 'success'], 200, [], JSON_NUMERIC_CHECK);
    }
}
