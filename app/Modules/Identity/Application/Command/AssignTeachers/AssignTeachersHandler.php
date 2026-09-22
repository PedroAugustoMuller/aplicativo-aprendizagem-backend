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

        $teachers = [];
        foreach (array_unique($command->teacherIds) as $teacherId) {
            $user = $this->users->findById(new UserId($teacherId));
            if ($user === null || ! $user->role()->isStaff() || ! $user->isActive()) {
                throw new TeacherNotFoundException;
            }
            $teachers[] = $user;
        }

        $classroom->assignTeachers($teachers);
        $this->classrooms->save($classroom);

        return ClassroomView::of($classroom);
    }
}
