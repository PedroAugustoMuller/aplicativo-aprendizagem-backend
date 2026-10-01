<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query\ListStudents;

use App\Modules\Identity\Application\Query\ListTeachers\AccountListItem;
use App\Modules\Identity\Application\Query\ListTeachers\AccountListReader;
use App\Modules\Identity\Domain\Exception\AccessDeniedException;
use App\Modules\Identity\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Identity\Domain\Policy\RosterPolicy;
use App\Modules\Identity\Domain\Repository\ClassroomRepository;
use App\Modules\Identity\Domain\ValueObject\ClassroomId;

final readonly class ListStudentsHandler
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private RosterPolicy $policy,
        private AccountListReader $reader,
    ) {}

    /** @return list<AccountListItem> */
    public function handle(ListStudentsQuery $query): array
    {
        // Inactive is fine for staff here: a teacher must still be able to see the
        // roster of a classroom that was closed for enrolment.
        $classroom = $this->classrooms->findById(new ClassroomId($query->classroomId))
            ?? throw new ClassroomNotFoundException;

        if (! $this->policy->canManageClassroom($query->actor, $classroom)) {
            throw new AccessDeniedException;
        }

        return $this->reader->studentsOf($query->classroomId);
    }
}
