<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Http\Controller;

use App\Modules\Identity\Application\Command\CreateStudents\CreateStudentsCommand;
use App\Modules\Identity\Application\Command\CreateStudents\CreateStudentsHandler;
use App\Modules\Identity\Application\Command\EnrolStudent\EnrolStudentCommand;
use App\Modules\Identity\Application\Command\EnrolStudent\EnrolStudentHandler;
use App\Modules\Identity\Application\Command\ResetStudentPassword\ResetStudentPasswordCommand;
use App\Modules\Identity\Application\Command\ResetStudentPassword\ResetStudentPasswordHandler;
use App\Modules\Identity\Application\Command\SetStudentActive\SetStudentActiveCommand;
use App\Modules\Identity\Application\Command\SetStudentActive\SetStudentActiveHandler;
use App\Modules\Identity\Application\Command\UnenrolStudent\UnenrolStudentCommand;
use App\Modules\Identity\Application\Command\UnenrolStudent\UnenrolStudentHandler;
use App\Modules\Identity\Application\DTO\AccountView;
use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Application\DTO\IssuedCredential;
use App\Modules\Identity\Application\Query\ListCredentials\ListCredentialsHandler;
use App\Modules\Identity\Application\Query\ListCredentials\ListCredentialsQuery;
use App\Modules\Identity\Application\Query\ListStudents\ListStudentsHandler;
use App\Modules\Identity\Application\Query\ListStudents\ListStudentsQuery;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListItem;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Http\Request\CreateStudentsRequest;
use App\Shared\Infrastructure\Http\ActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentController
{
    public function __construct(private readonly ActorFactory $actors) {}

    public function index(Request $request, string $id, ListStudentsHandler $handler): JsonResponse
    {
        $items = $handler->handle(new ListStudentsQuery($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => array_map($this->presentListItem(...), $items)]);
    }

    /**
     * Two teachers submitting the same brand-new name (e.g. both paste "Ana
     * Souza") at the same moment can both compute the username "ana.souza": the
     * `isTaken` check races across requests, not just within one. The
     * `users.username` unique index is the backstop — the loser's transaction
     * fails with a QueryException, surfaced as a 500. Accepted at one school's
     * scale; no retry logic added for it.
     */
    public function store(CreateStudentsRequest $request, string $id, CreateStudentsHandler $handler, UserRepository $users): JsonResponse
    {
        /** @var list<array{id: string, name: string}> $students */
        $students = $request->input('students', []);

        $allExisted = true;
        foreach ($students as $row) {
            if ($users->findById(new UserId($row['id'])) === null) {
                $allExisted = false;
                break;
            }
        }

        $views = $handler->handle(new CreateStudentsCommand($this->actors->fromRequest($request), $id, $students));

        return new JsonResponse(['data' => array_map($this->present(...), $views)], $allExisted ? 200 : 201);
    }

    public function enrol(Request $request, string $id, string $studentId, EnrolStudentHandler $handler): JsonResponse
    {
        $view = $handler->handle(new EnrolStudentCommand($this->actors->fromRequest($request), $id, $studentId));

        return new JsonResponse(['data' => $this->presentClassroom($view)]);
    }

    public function unenrol(Request $request, string $id, string $studentId, UnenrolStudentHandler $handler): JsonResponse
    {
        $view = $handler->handle(new UnenrolStudentCommand($this->actors->fromRequest($request), $id, $studentId));

        return new JsonResponse(['data' => $this->presentClassroom($view)]);
    }

    public function resetPassword(Request $request, string $id, ResetStudentPasswordHandler $handler): JsonResponse
    {
        $view = $handler->handle(new ResetStudentPasswordCommand($this->actors->fromRequest($request), $id));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function deactivate(Request $request, string $id, SetStudentActiveHandler $handler): JsonResponse
    {
        $view = $handler->handle(new SetStudentActiveCommand($this->actors->fromRequest($request), $id, false));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    public function reactivate(Request $request, string $id, SetStudentActiveHandler $handler): JsonResponse
    {
        $view = $handler->handle(new SetStudentActiveCommand($this->actors->fromRequest($request), $id, true));

        return new JsonResponse(['data' => $this->present($view)]);
    }

    /**
     * Plaintext temporary passwords for printing — never cached, client or
     * intermediary side.
     */
    public function credentials(Request $request, string $id, ListCredentialsHandler $handler): JsonResponse
    {
        $slips = $handler->handle(new ListCredentialsQuery($this->actors->fromRequest($request), $id));

        return (new JsonResponse(['data' => array_map($this->presentCredential(...), $slips)]))
            ->header('Cache-Control', 'no-store');
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

    /** @return array{id: string, name: string, subject_id: string, teacher_ids: list<string>, student_count: int, active: bool} */
    private function presentClassroom(ClassroomView $view): array
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

    /** @return array{user_id: string, name: string, login: string, temporary_password: string} */
    private function presentCredential(IssuedCredential $credential): array
    {
        return [
            'user_id' => $credential->userId,
            'name' => $credential->name,
            'login' => $credential->login,
            'temporary_password' => $credential->temporaryPassword,
        ];
    }
}
