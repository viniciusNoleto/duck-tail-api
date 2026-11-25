<?php

namespace App\Models;

use App\Models\Concerns\FindsOrFailsByKey;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuário (Postgres). O grafo tem um nó `User` com o mesmo `id`, mantido pelo `UserObserver`.
 */
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable {

    use FindsOrFailsByKey, HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Busca por nome ou e-mail.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $search): void {
        $query->when($search, fn(Builder $query) => $query->where(
            fn(Builder $query) => $query->whereLike('name', '%' . escape_like($search) . '%')->orWhereLike('email', '%' . escape_like($search) . '%')
        ));
    }
}
