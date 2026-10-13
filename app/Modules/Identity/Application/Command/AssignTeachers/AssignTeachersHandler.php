<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command\AssignTeachers;

use App\Modules\Identity\Application\DTO\ClassroomView;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Exception\TeacherNotFoundException;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\Repository\UserRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;
use App\Modules\Identity\Domain\ValueObject\UserId;

final readonly class AssignTeachersHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private UserRepository $users,
    ) {}

    public function handle(AssignTeachersCommand $command): ClassroomView
    {
        if (! $command->actor->isAdmin()) {
            throw new AccessDeniedException;
        }

        $classroom = $this->classrooms->findById(new ClassroomId($command->classroomId))
            ?? throw new ClassroomNotFoundException;

        $users = [];
        $assignments = [];
        foreach ($command->assignments as $row) {
            $user = $users[$row['teacherId']] ??= $this->users->findById(new UserId($row['teacherId']));
            if ($user === null || ! $user->role()->isStaff() || ! $user->isActive()) {
                throw new TeacherNotFoundException;
            }
            $assignments[] = [$user, $row['subjectId']];
        }

        $classroom->replaceAssignments($assignments);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
