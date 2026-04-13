<?php

namespace Webkul\Admin;

use Webkul\User\Repositories\UserRepository;

class Bouncer
{
    /**
     * Checks if user allowed or not for certain action
     *
     * @param  string  $permission
     * @return void
     */
    public function hasPermission($permission)
    {
        if (! auth()->guard('user')->check()) {
            return false;
        }

        $user = auth()->guard('user')->user();

        // Si es administrador, tiene todo
        if ($user->role->permission_type == 'all') {
            return true;
        }

        // Si tiene el permiso exacto, permitir
        if ($user->hasPermission($permission)) {
            return true;
        }

        // Fallback: Si estamos en actividades o etiquetas, permitir si tiene algún acceso a Leads
        if (str_contains($permission, 'activities') || str_contains($permission, 'tags')) {
            // Buscamos cualquier permiso que empiece por "leads" en el array de permisos del rol
            $rolePermissions = $user->role->permissions ?? [];

            foreach ($rolePermissions as $p) {
                if (str_starts_with($p, 'leads')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if user allowed or not for certain action
     *
     * @param  string  $permission
     * @return void
     */
    public static function allow($permission)
    {
        if (! bouncer()->hasPermission($permission)) {
            abort(401, 'This action is unauthorized');
        }
    }

    /**
     * This function will return user ids of current user's groups
     *
     * @return array|null
     */
    public function getAuthorizedUserIds()
    {
        $user = auth()->guard('user')->user();

        if ($user->view_permission == 'global') {
            return null;
        }

        if ($user->view_permission == 'group') {
            return app(UserRepository::class)->getCurrentUserGroupsUserIds();
        } else {
            return [$user->id];
        }
    }
}
