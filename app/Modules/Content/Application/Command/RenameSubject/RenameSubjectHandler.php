<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\RenameSubject;

use App\Modules\Content\Application\DTO\SubjectView;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectNameAlreadyTakenException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;

final readonly class RenameSubjectHandler
{
    public function __construct(private SubjectRepository $subjects) {}

    public function handle(RenameSubjectCommand $command): SubjectView
    {
        if (! $command->actor->isAdmin()) {
            throw new ContentAccessDeniedException;
        }

        $id = new SubjectId($command->id);
        $subject = $this->subjects->findById($id) ?? throw new SubjectNotFoundException;
        $name = new SubjectName($command->name);

        if ($this->subjects->nameTakenByAnother($name, $id)) {
            throw new SubjectNameAlreadyTakenException($name->value());
        }

        $subject->rename($name);
        $this->subjects->save($subject);

        return SubjectView::of($subject);
    }
}
