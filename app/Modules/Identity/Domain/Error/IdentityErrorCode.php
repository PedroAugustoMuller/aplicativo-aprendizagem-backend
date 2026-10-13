<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Error;

use App\Shared\Domain\Error\ErrorCode;

enum IdentityErrorCode: string implements ErrorCode
{
    case InvalidCredentials = 'identity.invalid_credentials';
    case AccountDeactivated = 'identity.account_deactivated';
    case PasswordChangeRequired = 'identity.password_change_required';
    case CurrentPasswordInvalid = 'identity.current_password_invalid';
    case NewPasswordUnchanged = 'identity.new_password_unchanged';
    case ClassroomNotFound = 'identity.classroom_not_found';
    case ClassroomNameAlreadyTaken = 'identity.classroom.name_already_taken';
    case ClassroomSubjectInactive = 'identity.classroom.subject_inactive';
    case ClassroomSubjectNotAssigned = 'identity.classroom.subject_not_assigned';
    case ClassroomTeacherAssignmentRedundant = 'identity.classroom.teacher_assignment_redundant';
    case TeacherNotFound = 'identity.teacher_not_found';
    case EnrolmentRequiresActiveStudent = 'identity.classroom.enrolment_requires_active_student';
    case EmailAlreadyTaken = 'identity.email_already_taken';
    case StudentNotFound = 'identity.student_not_found';

    public function code(): string
    {
        return $this->value;
    }
}
