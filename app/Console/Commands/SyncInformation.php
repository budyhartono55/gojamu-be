<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Information;

class SyncInformation extends Command
{
    protected $signature = 'sync:information';
    protected $description = 'Sync data (information) from pembantu to utama';

    public function handle()
    {
        Log::channel("job")->info("Sync Information from PPID-PEMBANTU to PPID-UTAMA: running at " . now()->toDateTimeString());

        $this->syncDataToEndpoint("INSERT");
        $this->syncDataToEndpoint("UPDATE");
        $this->syncDataToEndpoint("DELETE");
    }

    private function syncDataToEndpoint($action)
    {
        $data = null;
        switch ($action) {
            case 'INSERT':
                $data = Information::where('isNew', true)->get();
                break;
            case 'UPDATE':
                $data = Information::where('isSync', false)->get();
                break;
            case 'DELETE':
                $data = Information::withTrashed()
                    ->whereNotNull('deleted_at')
                    ->get();
                break;
            default:
                break;
        }
        //ctgInfoHandling
        $ctg = array(
            "informasi-berkala" => 1,
            "informasi-setiap-saat" => 2,
            "informasi-serta-merta" => 3,
            "informasi-terkecualikan" => 4,
        );
        try {
            if ($data->isEmpty()) {
                Log::channel("job")->info("You're ready up to date:" . $action);
                return;
            }

            $headers = [
                'AccessKey' => env("ACCESS_KEY"),
                'Accept-Charset' => 'UTF-8',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            foreach ($data as $information) {
                $payload = [
                    'preqinfocode' => $information->kode_informasi,
                    'pjenisinfo' => $ctg[$information->ctgInformation->slug],
                    'popdid' => env('OPD_ID'),
                    'pdetailinfo' => $information->description,
                    'pjudul' => $information->title_informasi,
                    'plinkdata' => env('FILE_URL') . $information->slug,
                    'pupdateat' => date('Y-m-d H:i:s', strtotime($information->created_at)),
                    'pstate' => $action,
                ];
                Log::channel("job")->info('Payload Data:', $payload);

                $response = Http::withHeaders($headers)->post('https://mantra.sumbawabaratkab.go.id/json/diskominfo/ppid/syncdatainformasi', $payload);

                if ($response->successful()) {
                    if ($action === "INSERT") {
                        $information->isSync = true;
                        $information->isNew = false;
                    } elseif ($action === "UPDATE") {
                        $information->isSync = true;
                        $information->isNew = false;
                    } elseif ($action === "DELETE") {
                        Information::withTrashed()->whereNotNull('deleted_at')->forceDelete();
                    }

                    $information->save();
                    Log::channel("job")->info('Sync:' . ucfirst(strtolower($action)) . '-Information Completed');
                    Log::channel("job")->info('Response Body: ' . $response->body());
                } else {
                    Log::channel("job")->error('Sync:' . ucfirst(strtolower($action)) . '-Failed to sync information');
                    Log::channel("job")->error('Response Body: ' . $response->body());
                }
            }
        } catch (\Throwable $e) {
            Log::channel("job")->error('Error saat melakukan sync data: ' . $e->getMessage());
        }
    }
}
