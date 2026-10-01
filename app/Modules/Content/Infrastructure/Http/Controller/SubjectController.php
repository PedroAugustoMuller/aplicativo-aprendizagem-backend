<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Http\Controller;

use App\Modules\Content\Application\Command\CreateSubject\CreateSubjectCommand;
use App\Modules\Content\Application\Command\CreateSubject\CreateSubjectHandler;
use App\Modules\Content\Application\Command\DeactivateSubject\DeactivateSubjectCommand;
use App\Modules\Content\Application\Command\DeactivateSubject\DeactivateSubjectHandler;
use App\Modules\Content\Application\Command\RenameSubject\RenameSubjectCommand;
use App\Modules\Content\Application\Command\RenameSubject\RenameSubjectHandler;
use App\Modules\Content\Application\DTO\SubjectView;
use App\Modules\Content\Application\Query\ListSubjects\ListSubjectsHandler;
use App\Modules\Content\Application\Query\ListSubjects\ListSubjectsQuery;
use App\Modules\Content\Application\Query\ListSubjects\SubjectListItem;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Infrastructure\Http\Request\CreateSubjectRequest;
use App\Modules\Content\Infrastructure\Http\Request\RenameSubjectRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubjectController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function index(Request $request, ListSubjectsHandler $handler): JsonResponse
    {
        $items = $handler->handle(new ListSubjectsQuery($this->actors->fromRequest($request)));

        return new JsonResponse(['data' => array_map(
            fn (SubjectListItem $s): array => ['id' => $s->id, 'name' => $s->name, 'active' => $s->active],
            $items,
        )]);
    }

    public function store(CreateSubjectRequest $request, CreateSubjectHandler $handler, SubjectRepository $subjects): JsonResponse
    {
        $existed = $subjects->findById(new SubjectId((string) $request->string('id'))) !== null;

        $view = $handler->handle(new CreateSubjectCommand(
            $this->actors->fromRequest($request),
            (string) $request->string('id'),
            (string) $request->string('name'),
        ));

        return new JsonResponse(['data' => $this->present($view)], $existed ? 200 : 201);
    }

    public function update(RenameSubjectRequest $request, string $id, RenameSubjectHandler $handler): JsonResponse
    {
        $view = $handler->handle(new RenameSubjectCommand(
            $this->actors->fromRequest($request),
            $id,
            (string) $request->string('name'),
        ));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function deactivate(Request $request, string $id, DeactivateSubjectHandler $handler): JsonResponse
    {
        $view = $handler->handle(new DeactivateSubjectCommand($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    /** @return array{id: string, name: string, active: bool} */
    private function present(SubjectView $view): array
    {
        return ['id' => $view->id, 'name' => $view->name, 'active' => $view->active];
    }
}
