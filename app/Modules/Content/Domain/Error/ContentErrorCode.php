<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Error;

use App\Shared\Domain\Error\ErrorCode;

enum ContentErrorCode: string implements ErrorCode
{
    case SubjectNotFound = 'content.subject_not_found';
    case SubjectNameAlreadyTaken = 'content.subject.name_already_taken';
    case SubjectInactive = 'content.subject.inactive';
    case TopicNotFound = 'content.topic_not_found';
    case TopicNameAlreadyTaken = 'content.topic.name_already_taken';
    case TopicOrderStale = 'content.topic.order_stale';
    case QuestionNotFound = 'content.question_not_found';
    case QuestionInvalidOptions = 'content.question.invalid_options';
    case QuestionEditedElsewhere = 'content.question.edited_elsewhere';

    public function code(): string
    {
        return $this->value;
    }
}
