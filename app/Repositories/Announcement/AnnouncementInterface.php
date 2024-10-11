<?php

namespace App\Repositories\Announcement;

interface AnnouncementInterface
{
    public function get($request);
    public function save($request);
    public function update($request, $id);
    public function delete($id);
    public function deletePermanent($id);
    public function restore();
    public function restoreById($id);
}
