<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\Information;
use Illuminate\Support\Facades\Log;


class Job_UpdateInformation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $dataToUpdate = Information::where('isSync', false)->get();
        try {
            if ($dataToUpdate->isEmpty()) {
                Log::info('No data to sync:update');
                return;
            }
            $headers = [
                'AccessKey' => env("ACCESS_KEY"),
                'Accept-Charset' => 'UTF-8',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            foreach ($dataToUpdate as $information) {
                $payload = [
                    'preqinfocode' => $information->kode_informasi,
                    'pjenisinfo' => $information->ctg_information_id,
                    'popdid' => env("OPD_CODE"),
                    'pdetailinfo' => $information->description,
                    'pjudul' => $information->title_informasi,
                    'plinkdata' => $information->url,
                    'pupdateat' => $information->created_at,

                    //action
                    'pstate' => "UPDATE",
                ];

                // Send
                $response = Http::withHeaders($headers)->post('http://mantra.diskominfo.sumbawabaratkab.go.id/json/diskominfo/ppid/syncdatainformasi', $payload);

                if ($response->successful()) {
                    $information->isSync = true;
                    $information->save();

                    Log::info('Sync Information Completed');
                } else {
                    Log::error('Failed to sync information');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error dalam job queue: ' . $e->getMessage());
        }
    }
}
