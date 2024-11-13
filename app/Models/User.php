<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'address',
        'contact',
        'image',
        'username'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $dates = ['deleted_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    // relasi ke table news 
    public function news()
    {
        return $this->hasMany(News::class);
    }
    public function medias()
    {
        return $this->hasMany(Media::class, 'user_id', 'id');
    }
    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'user_id', 'id');
    }
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }
    public function likes()
    {
        return $this->hasMany(Like::class, 'user_id', 'id');
    }
    public function ratings()
    {
        return $this->hasMany(Rating::class, 'user_id', 'id');
    }
    public function reports()
    {
        return $this->hasMany(Report::class, 'user_id', 'id');
    }
    public function favoriteBooks()
    {
        return $this->belongsToMany(Book::class, 'book_favorite_user')->withTimestamps()->withPivot('marked_at');
    }
}
