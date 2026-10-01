<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Controller;

use App\Modules\Identity\Application\Command\AssignTeachers\AssignTeachersCommand;
use App\Modules\Identity\Application\Command\AssignTeachers\AssignTeachersHandler;
use App\Modules\Identity\Application\Command\CreateClassroom\CreateClassroomCommand;
use App\Modules\Identity\Application\Command\CreateClassroom\CreateClassroomHandler;
use App\Modules\Identity\Application\Command\DeactivateClassroom\DeactivateClassroomCommand;
use App\Modules\Identity\Application\Command\DeactivateClassroom\DeactivateClassroomHandler;
use App\Modules\Identity\Application\Command\UpdateClassroom\UpdateClassroomCommand;
use App\Modules\Identity\Application\Command\UpdateClassroom\UpdateClassroomHandler;
use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Application\Query\ListClassrooms\ClassroomListItem;
use App\Modules\Identity\Application\Query\ListClassrooms\ListClassroomsHandler;
use App\Modules\Identity\Application\Query\ListClassrooms\ListClassroomsQuery;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Infrastructure\Http\Request\AssignTeachersRequest;
use App\Modules\Identity\Infrastructure\Http\Request\CreateClassroomRequest;
use App\Modules\Identity\Infrastructure\Http\Request\UpdateClassroomRequest;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClassroomController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function index(Request $request, ListClassroomsHandler $handler): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $items = $handler->handle(new ListClassroomsQuery($actor));

        return new JsonResponse(['data' => array_map(
            fn (ClassroomListItem $item): array => $this->presentListItem($item, $actor),
            $items,
        )]);
    }

    public function store(CreateClassroomRequest $request, CreateClassroomHandler $handler, ClassroomRepository $classrooms): JsonResponse
    {
        $existed = $classrooms->findById(new ClassroomId((string) $request->string('id'))) !== null;

        $view = $handler->handle(new CreateClassroomCommand(
            $this->actors->fromRequest($request),
            (string) $request->string('id'),
            (string) $request->string('name'),
            (string) $request->string('subject_id'),
        ));

        return new JsonResponse(['data' => $this->present($view)], $existed ? 200 : 201);
    }

    public function update(UpdateClassroomRequest $request, string $id, UpdateClassroomHandler $handler): JsonResponse
    {
        $view = $handler->handle(new UpdateClassroomCommand(
            $this->actors->fromRequest($request),
            $id,
            (string) $request->string('name'),
            (string) $request->string('subject_id'),
        ));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function assignTeachers(AssignTeachersRequest $request, string $id, AssignTeachersHandler $handler): JsonResponse
    {
        /** @var list<string> $teacherIds */
        $teacherIds = $request->input('teacher_ids', []);

        $view = $handler->handle(new AssignTeachersCommand(
            $this->actors->fromRequest($request),
            $id,
            $teacherIds,
        ));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function deactivate(Request $request, string $id, DeactivateClassroomHandler $handler): JsonResponse
    {
        $view = $handler->handle(new DeactivateClassroomCommand($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    /** @return array{id: string, name: string, subject_id: string, teacher_ids: list<string>, student_count: int, active: bool} */
    private function present(ClassroomView $view): array
    {
        return [
            'id' => $view->id,
            'name' => $view->name,
            'subject_id' => $view->subjectId,
            'teacher_ids' => $view->teacherIds,
            'student_count' => $view->studentCount,
            'active' => $view->active,
        ];
    }

    /** @return array{id: string, name: string, subject_id: string, teacher_ids: list<string>, student_count: int, active: bool} */
    private function presentListItem(ClassroomListItem $item, Actor $actor): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'subject_id' => $item->subjectId,
            // Students have no use for staff ids; only the admin UI pre-selects teachers from it.
            'teacher_ids' => $actor->isStudent() ? [] : $item->teacherIds,
            'student_count' => $item->studentCount,
            'active' => $item->active,
        ];
    }
}
