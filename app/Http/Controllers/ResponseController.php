<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Auth;
use DB;

use App\Helpers\Obfuscate;

use App\Models\Fafsa;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Response;
use Illuminate\Support\Facades\Crypt;

class ResponseController extends Controller
{
    public function __construct()
    {

        $this->middleware('auth');
    }

    // -------------------------------------------------------------------------

    public function update(Request $request, $id = null)
    {
        $demographics = (stripos($request->fullUrl(), '/fafsa') === false);

        if (!$demographics) {
            $fafsa = Fafsa::findOrFail(Fafsa::hash()->decode($id));
        }

        $sync = [];
        $safe = [];

        // Iterate Responses with Questions as Key
        foreach ($request->input('responses') as $question => $responses) {
            $question = Question::where('name', 'LIKE', $question)->firstOrFail();

            // Convert Single Responses to Array for Iteration
            if (!is_array($responses)) {
                $responses = [$responses];
            }

            // Determine Expected Response Type
            $type = [1 => 'boolean', 2 => 'select', 3 => 'multi', 4 => 'numeric', 5 => 'text', 6 => 'date'][$question->type];

            foreach ($responses as $response) {
                if (($type == 'boolean' && !is_bool($response)) ||
                    ($type == 'numeric' && !is_int($response)) ||
                    ($type == 'date' && !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[0-9]|[1-2][0-9]|3[0-1])$/', $response)) ||
                    (($type == 'select' || $type == 'multi' || $type == 'text') && !is_string($response))
                ) {
                    return response()->json(['result' => 'error', 'error' => 'invalid_data_type'], 400, [], JSON_NUMERIC_CHECK);
                }

                if ($type == 'boolean') {
                    $sync[$question->id] = [
                        'answer_id'         =>  null,
                        'data_boolean'      =>  $response,
                        'data_numeric'      =>  null,
                        'data_text'         =>  null,
                        'data_date'         =>  null
                    ];
                } else if ($type == 'date') {
                    $sync[$question->id] = [
                        'answer_id'         =>  null,
                        'data_boolean'      =>  null,
                        'data_numeric'      =>  null,
                        'data_text'         =>  null,
                        'data_date'         =>  $response
                    ];
                } else if ($type == 'select' || $type == 'multi') {
                    $answer = Answer::where('question_id', '=', $question->id)
                        ->where('name', 'LIKE', $response)
                        ->first();

                    // Check for Answer Already Attached
                    if ($demographics) {
                        if (Response::where('user_id', '=', Auth::id())
                            ->where('answer_id', '=', $answer->id)
                            ->whereNull('deleted_at')
                            ->count() == 0
                        ) {
                            // Attach New Responses
                            Auth::user()->responses()->attach([$question->id => [
                                'answer_id'         =>  $answer->id,
                                'data_boolean'      =>  null,
                                'data_numeric'      =>  null,
                                'data_text'         =>  null,
                                'data_date'         =>  null
                            ]]);
                        }
                    } else {
                        if (Response::where('fafsa_id', '=', $fafsa->id)
                            ->where('answer_id', '=', $answer->id)
                            ->whereNull('deleted_at')
                            ->count() == 0
                        ) {
                            // Attach New Responses
                            $fafsa->responses()->attach([$question->id => [
                                'answer_id'         =>  $answer->id,
                                'data_boolean'      =>  null,
                                'data_numeric'      =>  null,
                                'data_text'         =>  null,
                                'data_date'         =>  null
                            ]]);
                        }
                    }


                    // Check that Question Exists in Cache
                    if (!isset($safe[$question->id])) {
                        $safe[$question->id] = [];
                    }

                    // Add Answer to List of Deletion Exclusions
                    array_push($safe[$question->id], $answer->id);
                } else if ($type == 'text') {
                    $sync[$question->id] = [
                        'answer_id'       =>  null,
                        'data_boolean'    =>  null,
                        'data_numeric'    =>  null,
                        'data_text'       =>  $response,
                        'data_date'       =>  null
                    ];
                } else if ($type == 'numeric') {
                    $sync[$question->id] = [
                        'answer_id'       =>  null,
                        'data_boolean'    =>  null,
                        'data_numeric'    =>  $response,
                        'data_text'       =>  null,
                        'data_date'       =>  null
                    ];
                }
            }
        }

        // Attach/Detach Single Responses
        if (count($sync)) {
            $fafsa->responses()->syncWithoutDetaching($sync);
        }

        // Remove Unselected Multi-Select Options
        if (count($safe)) {
            foreach ($safe as $question => $values) {
                Response::query()
                    ->where('question_id', '=', $question)
                    ->whereNotIn('answer_id', $values)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => date('c')
                    ]);
            }
        }

        return response()->json(['result' => 'success'], 200, [], JSON_NUMERIC_CHECK);
    }

    public function getAllAnswer($fafsa_id, $user_id)
    {

        $fafsaId = $fafsa_id;
        $userId = $user_id;
        $query = "SELECT q.name AS question,IFNULL(a.name, IFNULL(r.data_boolean, IFNULL(r.data_numeric, IFNULL(r.data_text, r.data_date)))) AS response FROM FAFSA f LEFT JOIN Response r ON r.fafsa_id = f.id LEFT JOIN Question q ON q.id = r.question_id LEFT JOIN Answer a ON a.id = r.answer_id WHERE f.ID = '" . $fafsaId . "' AND  r.user_id ='" . $userId . "' AND r.deleted_at IS NULL";
        $getAnswerData = DB::connection('sql-app')->select($query);


        foreach ($getAnswerData as $key => $value) {
            if ($value->question == "user__ssn" || $value->question == "user__key") {
                $value->response = (string)Crypt::decrypt($value->response);
            }
        }

        return  response()->json($getAnswerData, 200, []);
    }

    public function getUseSsnKey(Request $request)
    {
        $userId = $request->user_id;
        $userSSN = Crypt::encrypt($request->user_ssn);
        $userKey = Crypt::encrypt($request->user_key);
        $secretData = array($userSSN, $userKey);

        //fetch question id in question table
        $selectQuestionResponse = Question::where('name', 'user__ssn')
            ->orWhere('name', 'user__key')
            ->get();
        $questionId = array();
        $selectExitSSN = array();
        foreach ($selectQuestionResponse as $value) {
            //check ssn or secret exit or not
            // $selectExitSSN[] = Response::select('response.data_text')->where('question_id', $value->id)->get();
            if ($value->name == 'user__ssn') {
                $questionId[0] = $value->id;
            } else {
                $questionId[1] = $value->id;
            }
        }

        //user wise fetch all question id
        $allQuestionIdByUser = array();
        $selectAllQuestionByUserResponse = Response::select("question_id")
            ->where("user_id", $userId)
            ->get();
        foreach ($selectAllQuestionByUserResponse as $value) {
            $allQuestionIdByUser[] = $value->question_id;
        }

        if (!empty($userId) && !empty($request->user_ssn) && !empty($request->userKey)) {
            for ($i = 0; $i < count($secretData); $i++) {
                //check user wise question id exit or not
                if (in_array($questionId[$i], $allQuestionIdByUser)) {
                    $jsonResponse['msg'] = "This question answer already added";
                } else {
                    $fetchResponseQueryData = Response::orderBy('id', 'desc')->first();

                    // answer id auto increment
                    $answerId = $fetchResponseQueryData->id + 1;

                    //Insert data into response table
                    $response = DB::connection('sql-app')->table('response')->insert(
                        [
                            'id' => $answerId,
                            'fafsa_id' => 1,
                            'user_id' => $userId,
                            'question_id' => $questionId[$i],
                            'data_text' => $secretData[$i]
                        ]
                    );
                    if ($response) {
                        $jsonResponse['msg'] = "successfully inserted";
                    } else {
                        $jsonResponse['msg'] = "not inserted";
                    }
                }
            }
        } else {
            $jsonResponse['msg'] = "Please enter SSN and save key";
        }

        return response()->json($jsonResponse, 200, [], JSON_NUMERIC_CHECK);
    }

    public function addUserAnswer(Request $request)
    {
        if ((!empty($request->fafsa_id) && !empty($request->user_id) && !empty($request->question_id)) && (($request->data_boolean != NULL) || ($request->data_numeric != NULL) || ($request->data_text != NULL) || ($request->data_date != NULL))) {
            //fetch question id in question table
            $selectQuestionResponse = Question::where('name', 'user__ssn')
                ->orWhere('name', 'user__key')
                ->get();
            $questionId = array();

            foreach ($selectQuestionResponse as $value) {
                //check ssn or secret exit or not
                if ($value->name == 'user__ssn') {
                    $questionId[0] = $value->id;
                } else {
                    $questionId[1] = $value->id;
                }
            }

            //user wise fetch all question id
            $allQuestionIdByUser = array();
            $selectAllQuestionByUserResponse = Response::select("question_id")
                ->where("user_id", $request->user_id)
                ->get();
            foreach ($selectAllQuestionByUserResponse as $value) {
                $allQuestionIdByUser[] = $value->question_id;
            }

            if (in_array($request->question_id, $allQuestionIdByUser)) {

                $jsonResponse['msg'] = "This question answer already added";
            } else {

                $fetchResponseQueryData = Response::orderBy('id', 'desc')->first();

                // answer id auto increment
                $answerId = $fetchResponseQueryData->id + 1;


                $dataBoolean = $request->data_boolean;
                $dataNumeric = $request->data_numeric;
                $dataText = $request->data_text;
                $dataDate = date('Y-m-d', strtotime($request->data_date));


                if (in_array($request->question_id, $questionId)) {
                    $dataText = Crypt::encrypt($dataText);
                }

                if ($dataBoolean != NULL) {
                    $key = 'data_boolean';
                    $value = $dataBoolean;
                } else if ($dataNumeric != NULL) {
                    $key = 'data_numeric';
                    $value = $dataNumeric;
                } else if ($dataText != NULL) {
                    $key = 'data_text';
                    $value = $dataText;
                } else {
                    $key = 'data_date';
                    $value = $dataDate;
                }

                //Insert data into response table
                $response = DB::connection('sql-app')->table('response')->insert([
                    'id' => $answerId,
                    'fafsa_id' => $request->fafsa_id,
                    'user_id' => $request->user_id,
                    'question_id' => $request->question_id,
                    $key  => $value
                ]);

                if ($response) {
                    $jsonResponse['msg'] = "successfully inserted";
                    $jsonResponse['id'] = $answerId;
                } else {
                    $jsonResponse['msg'] = "answer not inserted";
                }
            }
        } else {
            $jsonResponse['msg'] = "Please enter answer";
        }
        return response()->json($jsonResponse, 200, [], JSON_NUMERIC_CHECK);
    }
}