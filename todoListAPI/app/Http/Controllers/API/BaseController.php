<?php

declare(strict_types = 1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    public function sendResponse($result, string $message = "")
    {
    	$response = [
            'success' => true,
        ];

        if (!empty($result)) {
            $response["data"] = $result;
        }

        if (!empty($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 200);
    }

    public function sendError($error, $errorMessages = [], int $code = 404)
    {
    	$response = [
            'success' => false,
            'message' => $error,
        ];
        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
        
        return response()->json($response, $code);
    }
}