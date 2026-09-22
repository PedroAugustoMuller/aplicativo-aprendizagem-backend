<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Application;

use App\Modules\Content\Application\Command\DeactivateSubject\DeactivateSubjectCommand;
use App\Modules\Content\Application\Command\DeactivateSubject\DeactivateSubjectHandler;
use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;

final class DeactivateSubjectHandlerTest extends TestCase
{
    private const ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    public function test_an_admin_deactivates_a_subject(): void
    {
        $repo = $this->subjects();
        $repo->save(Subject::create(new SubjectId(self::ID), new SubjectName('Biologia')));

        $view = (new DeactivateSubjectHandler($repo))->handle(new DeactivateSubjectCommand($this->admin(), self::ID));

        self::assertFalse($view->active);
    }

    public function test_a_teacher_cannot_deactivate_a_subject(): void
    {
        $repo = $this->subjects();
        $repo->save(Subject::create(new SubjectId(self::ID), new SubjectName('Biologia')));

        $this->expectException(ContentAccessDeniedException::class);
        (new DeactivateSubjectHandler($repo))->handle(new DeactivateSubjectCommand(new Actor('t', Role::Teacher), self::ID));
    }

    public function test_deactivating_an_unknown_subject_is_not_found(): void
    {
        $this->expectException(SubjectNotFoundException::class);
        (new DeactivateSubjectHandler($this->subjects()))->handle(new DeactivateSubjectCommand($this->admin(), self::ID));
    }

    private function admin(): Actor
    {
        return new Actor('a', Role::Admin);
    }

    private function subjects(): SubjectRepository
    {
        return new class implements SubjectRepository
        {
            /** @var array<string, Subject> */
            private array $byId = [];

            public function findById(SubjectId $id): ?Subject
            {
                return $this->byId[$id->value()] ?? null;
            }

            public function nameTakenByAnother(SubjectName $name, SubjectId $except): bool
            {
                foreach ($this->byId as $subject) {
                    if (! $subject->id()->equals($except) && mb_strtolower($subject->name()->value()) === mb_strtolower($name->value())) {
                        return true;
                    }
                }

                return false;
            }

            public function save(Subject $subject): void
            {
                $this->byId[$subject->id()->value()] = $subject;
            }
        };
    }
}
