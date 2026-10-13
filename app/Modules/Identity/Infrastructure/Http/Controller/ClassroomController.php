<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Controller;

use App\Modules\Identity\Application\Command\AssignTeachers\AssignTeachersCommand;
use App\Modules\Identity\Application\Command\AssignTeachers\AssignTeachersHandler;
use App\Modules\Identity\Application\Command\CreateClassroom\CreateClassroomCommand;
use App\Modules\Identity\Application\Command\CreateClassroom\CreateClassroomHandler;
use App\Modules\Identity\Application\Command\DeactivateClassroom\DeactivateClassroomCommand;
use App\Modules\Identity\Application\Command\DeactivateClassroom\DeactivateClassroomHandler;
use App\Modules\Identity\Application\Command\ReplaceClassroomSubjects\ReplaceClassroomSubjectsCommand;
use App\Modules\Identity\Application\Command\ReplaceClassroomSubjects\ReplaceClassroomSubjectsHandler;
use App\Modules\Identity\Application\Command\UpdateClassroom\UpdateClassroomCommand;
use App\Modules\Identity\Application\Command\UpdateClassroom\UpdateClassroomHandler;
use App\Modules\Identity\Application\Query\ListClassrooms\ClassroomListItem;
use App\Modules\Identity\Application\Query\ListClassrooms\ListClassroomsHandler;
use App\Modules\Identity\Application\Query\ListClassrooms\ListClassroomsQuery;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Infrastructure\Http\Request\AssignTeachersRequest;
use App\Modules\Identity\Infrastructure\Http\Request\CreateClassroomRequest;
use App\Modules\Identity\Infrastructure\Http\Request\ReplaceClassroomSubjectsRequest;
use App\Modules\Identity\Infrastructure\Http\Request\UpdateClassroomRequest;
use App\Modules\Identity\Infrastructure\Http\Resource\ClassroomResource;
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
            static fn (ClassroomListItem $item): array => ClassroomResource::toArray($item, $actor->isStudent()),
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
            $this->subjectIds($request),
        ));

        return new JsonResponse(['data' => ClassroomResource::toArray($view)], $existed ? 200 : 201);
    }

    public function update(UpdateClassroomRequest $request, string $id, UpdateClassroomHandler $handler): JsonResponse
    {
        $view = $handler->handle(new UpdateClassroomCommand(
            $this->actors->fromRequest($request),
            $id,
            (string) $request->string('name'),
        ));

        return new JsonResponse(['data' => ClassroomResource::toArray($view)]);
    }

    public function assignTeachers(AssignTeachersRequest $request, string $id, AssignTeachersHandler $handler): JsonResponse
    {
        /** @var list<array{teacher_id: string, subject_id: string|null}> $rows */
        $rows = $request->input('assignments', []);

        $view = $handler->handle(new AssignTeachersCommand(
            $this->actors->fromRequest($request),
            $id,
            array_map(static fn (array $row): array => ['teacherId' => $row['teacher_id'], 'subjectId' => $row['subject_id']], $rows),
        ));

        return new JsonResponse(['data' => ClassroomResource::toArray($view)]);
    }

    public function replaceSubjects(ReplaceClassroomSubjectsRequest $request, string $id, ReplaceClassroomSubjectsHandler $handler): JsonResponse
    {
        $view = $handler->handle(new ReplaceClassroomSubjectsCommand($this->actors->fromRequest($request), $id, $this->subjectIds($request)));

        return new JsonResponse(['data' => ClassroomResource::toArray($view)]);
    }

    public function deactivate(Request $request, string $id, DeactivateClassroomHandler $handler): JsonResponse
    {
        $view = $handler->handle(new DeactivateClassroomCommand($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => ClassroomResource::toArray($view)]);
    }

    /** @return list<string> */
    private function subjectIds(Request $request): array
    {
        /** @var list<string> $ids */
        $ids = $request->input('subject_ids', []);

        return $ids;
    }
}
