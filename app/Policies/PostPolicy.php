<?php

namespace App\Policies;

use App\DTOs\Posts\PostDTO;
use App\Enums\PostStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;

class PostPolicy
{

    public function update(Authenticatable $user, PostDTO $post): Response
    {
        if (!method_exists($user, 'hasRole')) {
            return Response::deny('El método de verificación de rol de usuario no se encuentra.');
        }

        $status = is_string($post->status) ? $post->status : $post->status->value;

        // Regla 1: Admin o Reviewer pueden actualizar cualquier post
        if ($user->hasRole('admin') || $user->hasRole('reviewer')) {
            return Response::allow();
        }

        // Regla 2: No se puede actualizar si está publicado
        if ($status === PostStatus::PUBLISHED->value) {
            return Response::deny('No se puede modificar un post publicado.');
        }

        // Regla 3: No se puede actualizar si está archivado
        if ($status === PostStatus::ARCHIVED->value) {
            return Response::deny('No se puede modificar un post archivado.');
        }

        // Regla 4: El autor no puede actualizar si está pendiente de revisión
        if ($status === PostStatus::PENDING_REVIEW->value) {
            return Response::deny('No se puede modificar un post pendiente de revisión.');
        }

        // Regla 5: El propietario del post puede actualizarlo si es borrador o se solicitaron cambios
        if ($user->getAuthIdentifier() === $post->author?->id) {
            return Response::allow();
        }

        return Response::deny('El usuario no tiene permiso para actualizar este post.');
    }

    public function delete(Authenticatable $user, PostDTO $post): Response
    {
        if (!method_exists($user, 'hasRole')) {
            return Response::deny('El método de verificación de rol de usuario no se encuentra.');
        }

        $status = is_string($post->status) ? $post->status : $post->status->value;

        // Regla 1: No se puede borrar si está publicado
        // Evaluamos contra tu Enum moderno en lugar de un string mágico
        if ($status === PostStatus::PUBLISHED->value) {
            return Response::deny('No se puede borrar un post publicado.');
        }

        // Regla 2: Propietario o Admin
        // Asumiendo que tu modelo User tiene un método hasRole() o manejas los roles por DTO
        if ($user->getAuthIdentifier() === $post->author?->id || $user->hasRole('admin')) {
            return Response::allow();
        }

        return Response::deny('El usuario no tiene permiso para borrar este post.');
    }
}
