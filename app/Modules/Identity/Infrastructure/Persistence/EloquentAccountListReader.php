<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Query\ListTeachers\AccountListItem;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListReader;
use App\Shared\Domain\Auth\Role;
use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads straight into DTOs. Selecting only the displayed columns is the point:
 * hydrating aggregates to render a list is the cost this read model avoids.
 */
final class EloquentAccountListReader implements AccountListReader
{
    public function teachers(): array
    {
        $models = UserModel::query()
            ->select(['id', 'name', 'email', 'must_change_password', 'deactivated_at'])
            ->whereIn('role', [Role::Admin->value, Role::Teacher->value])
            ->orderBy('name')
            ->get();

        return $this->toItems($models, 'email');
    }

    public function studentsOf(string $classroomId): array
    {
        $models = UserModel::query()
            ->select(['users.id', 'users.name', 'users.username', 'users.must_change_password', 'users.deactivated_at'])
            ->join('classroom_students', 'classroom_students.user_id', '=', 'users.id')
            ->where('classroom_students.classroom_id', $classroomId)
            ->orderBy('users.name')
            ->get();

        return $this->toItems($models, 'username');
    }

    /**
     * @param  Collection<int, UserModel>  $models
     * @return list<AccountListItem>
     */
    private function toItems(Collection $models, string $loginAttribute): array
    {
        $items = [];
        foreach ($models as $model) {
            $items[] = new AccountListItem(
                id: EloquentAttribute::string($model->getKey(), 'users.id'),
                name: EloquentAttribute::string($model->getAttribute('name'), 'users.name'),
                login: EloquentAttribute::string($model->getAttribute($loginAttribute), 'users.'.$loginAttribute),
                mustChangePassword: $model->getAttribute('must_change_password') === true,
                active: $model->getAttribute('deactivated_at') === null,
            );
        }

        return $items;
    }
}
