<?php

namespace App\Repositories\Contact;

use App\Helpers\Helper;
use App\Models\Contact;
use App\Models\User;
use App\Repositories\Contact\ContactInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;

class ContactRepository implements ContactInterface
{
    private $contact;
    protected $generalRedisKeys;

    // 1 Day redis expired
    private $expired = 86400;
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
        $this->generalRedisKeys = "contact_";
    }

    public function getAllContacts()
    {
        try {
            $key = $this->generalRedisKeys . "All";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Contact from (CACHE)", $result);
            }

            // noArray
            $contact = Contact::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->first();

            if ($contact) {
                $contact->created_by = optional($contact->createdBy)->only(['id', 'name', 'level']);
                $contact->edited_by = optional($contact->editedBy)->only(['id', 'name', 'level']);

                unset($contact->createdBy, $contact->editedBy);

                Redis::set($key, json_encode($contact));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("Details Contact", $contact);
            }
            return $this->success("Daftar contact tidak ditemukan", []);

            // withArray
            //         $contact = Contact::with(['createdBy', 'editedBy'])
            //             ->latest('created_at')
            //             ->get();

            //         if ($contact->isNotEmpty()) {
            //             $modifiedData = $contact->map(function ($item) {
            //                 $item->created_by = optional($item->createdBy)->only(['id', 'name', 'level']);
            //                 $item->edited_by = optional($item->editedBy)->only(['id', 'name', 'level']);

            //                 unset($item->createdBy, $item->editedBy);
            //                 return $item;
            //             });

            //             Redis::set($key, json_encode($contact));
            //             Redis::expire($key, 60); // Cache for 60 seconds

            //             return $this->success("Details Contact", $contact);
            //         }
            //         return $this->error("Daftar contact tidak ditemukan", $contact);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys;
            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("Contact dengan ID = ($id) from (CACHE)", $result);
            }

            $contact = Contact::find($id);
            if ($contact) {
                $createdBy = User::select('name')->find($contact->created_by);
                $editedBy = User::select('name')->find($contact->edited_by);

                $contact->created_by = [
                    'id' => $contact->created_by,
                    'name' => $createdBy->name,
                ];

                $contact->edited_by = [
                    'id' => $contact->edited_by,
                    'name' => $editedBy->name,
                ];

                Redis::set($key . $id, json_encode($contact));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Contact dengan ID $id", $contact);
            } else {
                return $this->error("Not Found", "Contact dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function createContact($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'address'     => 'required',
                'contact'     => 'required',
                'email'     => 'required',
            ],
            [
                'address.required' => 'Uppss, title_category tidak boleh kosong!',
                'contact.required' => 'Uppss, contact tidak boleh kosong!',
                'email.required' => 'Uppss, email tidak boleh kosong!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $existingContact = Contact::first();

            if ($existingContact) {
                return $this->error("Already Exist", "Kontak sudah tersedia, silahkan lakukan pembaharuan!", 409);
            }
            $validator = Validator::make(
                $request->all(),
                [
                    'address'     => 'required',
                    'contact'     => 'required',
                    'email'     => 'required',
                ],
                [
                    'address.required' => 'Uppss, title_category tidak boleh kosong!',
                    'contact.required' => 'Uppss, contact tidak boleh kosong!',
                    'email.required' => 'Uppss, email tidak boleh kosong!',
                ]
            );

            //check if validation fails
            if ($validator->fails()) {
                return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
            }

            $contact = new Contact();
            $contact->address = $request->address; // required
            $contact->url_address = $request->url_address; // required
            $contact->contact = $request->contact; // required
            $contact->email = $request->email; // required
            $contact->facebook = $request->facebook;
            $contact->instagram = $request->instagram;
            $contact->twitter = $request->twitter;
            $contact->youtube = $request->youtube;
            $contact->tiktok = $request->tiktok;
            $contact->website = $request->website;

            $user = Auth::user();
            $contact->created_by = $user->id;
            $contact->edited_by = $user->id;

            $create = $contact->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Kontak Berhasil ditambahkan!", $contact);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function updateContact($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'address'     => 'required',
                'contact'     => 'required',
                'email'     => 'required',
            ],
            [
                'address.required' => 'Uppss, title_category tidak boleh kosong!',
                'contact.required' => 'Uppss, contact tidak boleh kosong!',
                'email.required' => 'Uppss, email tidak boleh kosong!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            // search
            $contact = Contact::find($id);
            // check
            if (!$contact) {
                return $this->error("Not Found", "Kontak dengan ID = ($id) tidak ditemukan!", 404);
            } else {

                $contact['address'] = $request->address;
                $contact['url_address'] = $request->url_address;
                $contact['contact'] = $request->contact;
                $contact['email'] = $request->email;
                $contact['facebook'] = $request->facebook;
                $contact['instagram'] = $request->instagram;
                $contact['twitter'] = $request->twitter;
                $contact['youtube'] = $request->youtube;
                $contact['tiktok'] = $request->tiktok;
                $contact['website'] = $request->website;

                $oldCreatedBy = $contact->created_by;
                $contact['created_by'] = $oldCreatedBy;
                $contact['edited_by'] = Auth::user()->id;

                $update = $contact->save();
                if ($update) {
                    RedisHelper::dropKeys($this->generalRedisKeys);
                    return $this->success("Kontak Berhasil diperharui!", $contact);
                }
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deleteContact($id)
    {
        try {
            // search
            $contact = Contact::find($id);
            if (!$contact) {
                return $this->error("Not Found", "Kontak dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $contact->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Kontak dengan ID = ($id) Berhasil dihapus!", "COMPLETED");
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
}
