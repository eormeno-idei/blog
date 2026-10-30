<?php

namespace App\Policies;

use App\DTOs\Posts\PostDTO;
use App\Enums\PostStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;

class PostPolicy
{
    public function delete(Authenticatable $user, PostDTO $post): Response
    {
        if (!method_exists($user, 'hasRole')) {
            return Response::deny('El método de verificación de rol de usuario no se encuentra.');
        }

        // Regla 1: No se puede borrar si está publicado
        // Evaluamos contra tu Enum moderno en lugar de un string mágico
        if ($post->status === PostStatus::PUBLISHED) {
            return Response::deny('No se puede borrar un post publicado.');
        }

        // Regla 2: Propietario o Admin
        // Asumiendo que tu modelo User tiene un método hasRole() o manejas los roles por DTO
        if ($user->getAuthIdentifier() === $post->author->id || $user->hasRole('admin')) {
            return Response::allow();
        }

        return Response::deny('El usuario no tiene permiso para borrar este post.');
    }
}
