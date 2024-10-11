<?php

namespace App\Helpers\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class EmailService
{
    public static function sendEmail($to_email, $contents, $to_name, $subject)
    {
        try {
            $payload = [
                "tos" => $to_email,
                "from_name" => env('MAIL_FROM_NAME'),
                "from" => env('MAIL_FROM_ADDRESS'),
                "subject" => $subject,
                "email_type" => 'Pemberitahuan Official PPID Kabupaten Sumbawa Barat',
                "contents" => $contents,
                "to_name" => $to_name,
                "created_by" => 'Official PPID Kabupaten Sumbawa Barat',
            ];

            // Jalur pengiriman email
            // $response = Http::post('https://apiportal.sumbawabaratkab.go.id/api/notif/sendemails', $payload);
            // if ($response->successful()) {
            //     Log::channel("email")->info("to: {$to_email}, Response Body: {$response->body()}");
            //     return ['success' => true, 'message' => "dan Email berhasil terkirim"];
            // } else {
            //     Log::channel("email")->error('Response Body: ' . $response->body());
            //     return ['success' => false, 'message' => "namun Email gagal terkirim"];
            // }
            $response = Http::post('https://apiportal.sumbawabaratkab.go.id/api/notif/sendemails', $payload);

            if ($response->successful()) {
                Log::channel("email")->info("to: {$to_email}, Response Body: {$response->body()}");
                return ['success' => true, 'message' => "dan Email berhasil terkirim"];
            } else {
                Log::channel("email")->error('Response Body: ' . $response->body());
                return ['success' => false, 'message' => "namun Email gagal terkirim"];
            }
        } catch (\Exception $e) {
            Log::channel("email")->error('Error: ' . $e->getMessage());
            return ['success' => false, 'message' => "Namun terjadi kesalahan: "];
        }
    }
}
