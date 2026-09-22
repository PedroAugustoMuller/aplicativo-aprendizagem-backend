<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Controller;

use App\Modules\Identity\Application\Command\CreateTeacher\CreateTeacherCommand;
use App\Modules\Identity\Application\Command\CreateTeacher\CreateTeacherHandler;
use App\Modules\Identity\Application\Command\ResetTeacherPassword\ResetTeacherPasswordCommand;
use App\Modules\Identity\Application\Command\ResetTeacherPassword\ResetTeacherPasswordHandler;
use App\Modules\Identity\Application\Command\SetTeacherActive\SetTeacherActiveCommand;
use App\Modules\Identity\Application\Command\SetTeacherActive\SetTeacherActiveHandler;
use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListItem;
use App\Modules\Identity\Application\Query\ListTeachers\ListTeachersHandler;
use App\Modules\Identity\Application\Query\ListTeachers\ListTeachersQuery;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Http\Request\CreateTeacherRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TeacherController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function index(Request $request, ListTeachersHandler $handler): JsonResponse
    {
        $items = $handler->handle(new ListTeachersQuery($this->actors->fromRequest($request)));

        return new JsonResponse(['data' => array_map($this->presentListItem(...), $items)]);
    }

    public function store(CreateTeacherRequest $request, CreateTeacherHandler $handler, UserRepository $users): JsonResponse
    {
        $existed = $users->findById(new UserId((string) $request->string('id'))) !== null;

        $view = $handler->handle(new CreateTeacherCommand(
            $this->actors->fromRequest($request),
            (string) $request->string('id'),
            (string) $request->string('name'),
            (string) $request->string('email'),
        ));

        return new JsonResponse(['data' => $this->present($view)], $existed ? 200 : 201);
    }

    public function resetPassword(Request $request, string $id, ResetTeacherPasswordHandler $handler): JsonResponse
    {
        $view = $handler->handle(new ResetTeacherPasswordCommand($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function deactivate(Request $request, string $id, SetTeacherActiveHandler $handler): JsonResponse
    {
        $view = $handler->handle(new SetTeacherActiveCommand($this->actors->fromRequest($request), $id, false));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function reactivate(Request $request, string $id, SetTeacherActiveHandler $handler): JsonResponse
    {
        $view = $handler->handle(new SetTeacherActiveCommand($this->actors->fromRequest($request), $id, true));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    /** @return array{id: string, name: string, login: string, role: string, must_change_password: bool, active: bool, temporary_password: ?string} */
    private function present(AccountView $view): array
    {
        return [
            'id' => $view->id,
            'name' => $view->name,
            'login' => $view->login,
            'role' => $view->role,
            'must_change_password' => $view->mustChangePassword,
            'active' => $view->active,
            'temporary_password' => $view->temporaryPassword,
        ];
    }

    /** @return array{id: string, name: string, login: string, must_change_password: bool, active: bool} */
    private function presentListItem(AccountListItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'login' => $item->login,
            'must_change_password' => $item->mustChangePassword,
            'active' => $item->active,
        ];
    }
}
