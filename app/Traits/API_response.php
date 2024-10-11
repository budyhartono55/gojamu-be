<?php

namespace App\Traits;

trait API_response
{
    /**
     * Core of response
     * 
     * @param   string          $message
     * @param   array|object    $data
     * @param   integer         $statusCode  
     * @param   boolean         $isSuccess
     */
    public function coreResponse($message, $data, $statusCode, $isSuccess = true)
    {
        // Check the params
        if (!$message) return response()->json(['message' => 'Message is required'], 500);
        // if ($statusCode >= 500) {
        //     return response()->json([
        //         'code' => $statusCode,
        //         'error' => true,
        //         'message' => $message
        //     ], $statusCode);
        // }
        return response()->json([
            'code' => $statusCode,
            'error' => $isSuccess ? false : true,
            'message' => $message,
            'results' => ($statusCode >= 500) ? [] : $data
        ], $statusCode);
    }

    /**
     * Send any success response
     * 
     * @param   string          $message
     * @param   array|object    $data
     * @param   integer         $statusCode
     */
    public function success($message, $data, $statusCode = 200)
    {
        return $this->coreResponse($message, $data, $statusCode);
    }

    /**
     * Send any error response
     * 
     * @param   string          $message
     * @param   integer         $statusCode    
     */
    public function error($message, $data, $statusCode = 500)
    {
        return $this->coreResponse($message, $data, $statusCode, false);
    }
}
