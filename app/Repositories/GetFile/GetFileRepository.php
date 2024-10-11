<?php

namespace App\Repositories\GetFile;

use App\Repositories\GetFile\GetFileInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class GetFileRepository implements GetFileInterface
{

    // Response API HANDLER
    use API_response;


    // getAll
    public function getFile($request)
    {
        try {
            $path = $request->path;
            $pathFile = storage_path('app/public/' . $path);

            if (File::isDirectory($pathFile)) {
                return $this->error("Not Found", "File Tidak Ditemukan", 404);
            }
            if (($path !== null) and ($path !== '""') and ($path !== "")) {
                $storage = Storage::disk('public');
                if ($storage->exists($path)) {
                    return response()->file($pathFile);
                }
                return $this->error("Not Found", "File Tidak Ditemukan", 404);
            }
            return $this->error("Not Found", "File Tidak Ditemukan", 404);
            // };

            //=========================
            // NO-REDIS
            // $kategori = GetFile::paginate(3);
            // return $this->success(" List kesuluruhan kategori", $kategori);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
