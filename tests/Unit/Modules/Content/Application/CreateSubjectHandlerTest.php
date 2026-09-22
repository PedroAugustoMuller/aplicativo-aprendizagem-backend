<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Application;

use App\Modules\Content\Application\Command\CreateSubject\CreateSubjectCommand;
use App\Modules\Content\Application\Command\CreateSubject\CreateSubjectHandler;
use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectNameAlreadyTakenException;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Exception\IdempotencyConflictException;
use PHPUnit\Framework\TestCase;

final class CreateSubjectHandlerTest extends TestCase
{
    private const ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f';

    private const OTHER_ID = '0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e10';

    public function test_an_admin_creates_a_subject(): void
    {
        $repo = $this->subjects();
        $view = (new CreateSubjectHandler($repo))->handle(new CreateSubjectCommand($this->admin(), self::ID, 'Biologia'));

        self::assertSame(self::ID, $view->id);
        self::assertSame('Biologia', $view->name);
        self::assertTrue($view->active);
    }

    public function test_a_teacher_cannot_create_a_subject(): void
    {
        $this->expectException(ContentAccessDeniedException::class);
        (new CreateSubjectHandler($this->subjects()))->handle(new CreateSubjectCommand(new Actor('t', Role::Teacher), self::ID, 'Biologia'));
    }

    public function test_replaying_the_same_id_and_name_returns_the_original(): void
    {
        $repo = $this->subjects();
        $handler = new CreateSubjectHandler($repo);
        $handler->handle(new CreateSubjectCommand($this->admin(), self::ID, 'Biologia'));

        $again = $handler->handle(new CreateSubjectCommand($this->admin(), self::ID, ' Biologia '));

        self::assertSame('Biologia', $again->name);
    }

    public function test_replaying_the_same_id_with_another_name_conflicts(): void
    {
        $handler = new CreateSubjectHandler($this->subjects());
        $handler->handle(new CreateSubjectCommand($this->admin(), self::ID, 'Biologia'));

        $this->expectException(IdempotencyConflictException::class);
        $handler->handle(new CreateSubjectCommand($this->admin(), self::ID, 'Física'));
    }

    public function test_a_name_used_by_another_subject_is_taken(): void
    {
        $handler = new CreateSubjectHandler($this->subjects());
        $handler->handle(new CreateSubjectCommand($this->admin(), self::ID, 'Biologia'));

        $this->expectException(SubjectNameAlreadyTakenException::class);
        $handler->handle(new CreateSubjectCommand($this->admin(), self::OTHER_ID, 'biologia'));
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
