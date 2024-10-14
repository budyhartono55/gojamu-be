<?php

namespace App\Repositories\Sponsor;

interface SponsorInterface
{
    public function getSponsor($request);
    public function save($request);
    public function update($request, $id);
    public function delete($id);
}
