<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Contact\ContactInterface;

class ContactController extends Controller
{
    private $contactRepository;

    public function __construct(ContactInterface $contactRepository)
    {
        $this->contactRepository = $contactRepository;
    }


    public function getAll()
    {

        return $this->contactRepository->getAllContacts();
    }

    public function getById($id)
    {

        return $this->contactRepository->findById($id);
    }

    public function add(Request $request)
    {
        return $this->contactRepository->createContact($request);
    }

    public function edit(Request $request, $id)
    {
        return $this->contactRepository->updateContact($request, $id);
    }

    public function delete($id)
    {
        return $this->contactRepository->deleteContact($id);
    }
}
