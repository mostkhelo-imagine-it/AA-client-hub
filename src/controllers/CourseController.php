<?php
declare(strict_types=1);

final class CourseController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $courses = Db::pdo()->query(
            'SELECT c.*,
                (SELECT COUNT(*) FROM course_records r WHERE r.course_id = c.id AND r.type = \'attended\') AS attended_count,
                (SELECT COUNT(*) FROM course_records r WHERE r.course_id = c.id AND r.type = \'purchased\') AS purchased_count
             FROM courses c ORDER BY c.title'
        )->fetchAll();

        render('courses/index', [
            'courses' => $courses,
            'canManage' => Access::canManageCourseCatalog(),
        ]);
    }

    public static function store(): void
    {
        Auth::requireLogin();

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Title is required.');
            redirect('/courses');
        }
        $type = ($_POST['type'] ?? 'online') === 'live' ? 'live' : 'online';

        $stmt = Db::pdo()->prepare(
            'INSERT INTO courses (title, type, price, platform) VALUES (:title, :type, :price, :platform)'
        );
        $stmt->execute([
            'title' => $title,
            'type' => $type,
            'price' => $_POST['price'] !== '' ? (float) $_POST['price'] : null,
            'platform' => trim((string) ($_POST['platform'] ?? '')) ?: null,
        ]);
        Activity::log('course.create', 'course', (int) Db::pdo()->lastInsertId());

        flash('success', 'Course added.');
        redirect('/courses');
    }
}
