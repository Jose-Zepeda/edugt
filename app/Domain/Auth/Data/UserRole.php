<?php

namespace App\Domain\Auth\Data;

enum UserRole: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    // RB-01: admin NO existe como opción pública de registro.
}
