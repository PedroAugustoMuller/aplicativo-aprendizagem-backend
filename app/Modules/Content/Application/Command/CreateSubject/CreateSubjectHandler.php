<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\CreateSubject;

use App\Modules\Content\Application\DTO\SubjectView;
use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectNameAlreadyTakenException;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use App\Shared\Domain\Exception\IdempotencyConflictException;

final readonly class CreateSubjectHandler
{
    public function __construct(private SubjectRepository $subjects) {}

    public function handle(CreateSubjectCommand $command): SubjectView
    {
        if (! $command->actor->isAdmin()) {
            throw new ContentAccessDeniedException;
        }

        $id = new SubjectId($command->id);
        $name = new SubjectName($command->name);
        $existing = $this->subjects->findById($id);

        if ($existing !== null) {
            if ($existing->name()->value() !== $name->value()) {
                throw new IdempotencyConflictException;
            }

            return SubjectView::of($existing);
        }

        if ($this->subjects->nameTakenByAnother($name, $id)) {
            throw new SubjectNameAlreadyTakenException($name->value());
        }

        $subject = Subject::create($id, $name);
        $this->subjects->save($subject);

        return SubjectView::of($subject);
    }
}
