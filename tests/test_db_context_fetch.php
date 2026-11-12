<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;

$db = Database::getConnection();

echo "Testing Siswa Query...\n";
$siswaStmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.nip_nisn, u.gender, 
           c.name as class_name, c.grade_level, c.major,
           w.name as homeroom_teacher_name,
           ay.name as academic_year_name, s.name as semester_name
    FROM users u
    LEFT JOIN class_students cs ON cs.student_id = u.id AND cs.status = 'active'
    LEFT JOIN classes c ON c.id = cs.class_id
    LEFT JOIN users w ON w.id = c.homeroom_teacher_id
    LEFT JOIN academic_years ay ON ay.id = cs.academic_year_id AND ay.is_active = 1
    LEFT JOIN semesters s ON s.academic_year_id = ay.id AND s.is_active = 1
    WHERE u.id = :user_id
    LIMIT 1
");
$siswaStmt->execute([':user_id' => 4]);
$siswaData = $siswaStmt->fetch();
print_r($siswaData);

echo "\nTesting Guru Query...\n";
$guruStmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.nip_nisn
    FROM users u
    WHERE u.id = :user_id
    LIMIT 1
");
$guruStmt->execute([':user_id' => 3]);
$guruData = $guruStmt->fetch();
print_r($guruData);

echo "\nTesting Admin User Stats Query...\n";
$adminStats = $db->query("
    SELECT r.name as role_name, COUNT(u.id) as total_users,
           SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active_users
    FROM roles r
    LEFT JOIN user_roles ur ON ur.role_id = r.id
    LEFT JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL
    GROUP BY r.id
")->fetchAll();
print_r($adminStats);

echo "\nALL QUERIES OK\n";
