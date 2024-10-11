<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Announcement\AnnouncementInterface;

class AnnouncementController extends Controller
{
    private $announcementRepository;

    public function __construct(AnnouncementInterface $announcementRepository)
    {
        $this->announcementRepository = $announcementRepository;
    }

    public function getAnnouncement(Request $request)
    {
        return $this->announcementRepository->get($request);
    }

    public function save(Request $request)
    {
        return $this->announcementRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->announcementRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->announcementRepository->delete($id);
    }

    public function deletePermanent($id)
    {
        return $this->announcementRepository->deletePermanent($id);
    }

    public function restore()
    {
        return $this->announcementRepository->restore();
    }

    public function restoreById($id)
    {
        return $this->announcementRepository->restoreById($id);
    }
}
