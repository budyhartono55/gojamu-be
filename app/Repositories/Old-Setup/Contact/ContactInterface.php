<?php

namespace App\Repositories\Contact;

interface ContactInterface
{
    public function getAllContacts();
    public function findById($id);
    public function createContact($request);
    public function updateContact($request, $id);
    public function deleteContact($id);
}
