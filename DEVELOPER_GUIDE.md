# School Management System — Developer Guide

Everything a new maintainer needs, in one file. Read this before changing anything.

---

## 1. What this is

A school management system in **plain PHP + MySQL** — no framework, no Composer,
no build step. You edit a `.php` file and refresh the browser.

| | |
|---|---|
| Runs on | XAMPP (Apache + MariaDB) on Windows |
| PHP | 8.2 |
| Database | `School_db1` |
| Lives in | `C:\xampp\htdocs\School_System(1)` |
| Open at | `http://localhost/School_System(1)/` |

**There are five kinds of user**, each with their own portal:

| Role | Folder | Lands on |
|---|---|---|
| admin | `admin/` | `admin/dashboard.php` |
| cashier | `admin/` *(finance pages only)* | `admin/finance.php` |
| teacher | `teacher/` | `teacher/dashboard.php` |
| student | `student/` | `student/dashboard.php` |
| parent | `parent/` | `parent/dashboard.php` |

---

## 2. How one page is built

Every page follows the same five steps. Copy this shape for a new page:

```php
<?php
// ============================================================
// admin/example.php - one line saying what this page is for.
//
// Who can open it: admin
// ============================================================
include '../config.php';        // 1. database ($conn), session, helpers
require_role('admin');          // 2. who is allowed - BEFORE any output

// 3. do the work: read $_POST, run queries, prepare variables
$rows = mysqli_query($conn, "SELECT * FROM students ORDER BY name");

include 'sidebar.php';          // 4. opens <html>, draws the menu and top bar
?>

<h2>My Page</h2>
<!-- 5. the page's own HTML -->

</div></div>                    <!-- closes the two divs sidebar.php opened -->
</body>
</html>
```

Two things catch people out:

- **`require_role()` must come before anything is printed.** It works by sending
  a redirect header, and a header cannot be sent once even one space has been
  output. This is also why no PHP file may start with a byte order mark.
- **`sidebar.php` opens `<html>` and two `<div>`s** and does not close them.
  Your page closes them with `</div></div></body></html>` at the bottom.
  The parent portal does this for you — end those pages with
  `<?php include 'footer.php'; ?>` instead.

---

## 3. The files

### Root — shared by everything

| File | What it does |
|---|---|
| `config.php` | **Loaded first by every page.** Connects to the database (`$conn`), starts the session, sets error reporting, loads `auth.php`. |
| `auth.php` | Who is allowed where: `require_role()`, `is_admin()`, `current_user_id()`. |
| `index.php` | The login page. |
| `login.php` | Checks the username and password, then sends the person to their own dashboard. |
| `logout.php` | Ends the session. |
| `responsive.css` / `responsive.js` | One shared layer that makes every page work on a phone. |

### Root — helpers (one subject each)

Each helper is a set of functions wrapped in `if (!function_exists(...))`, so
including it twice is harmless.

| Helper | Main functions | Use it when |
|---|---|---|
| `results_helper.php` | `calc_result()`, `rank_students()`, `result_status_badge()` | Anything showing a total, average, rank or PASS / FAIL |
| `lesson_plans_helper.php` | `lp_sections()`, `lp_list()`, `lp_render_plan()`, `lp_date()` | Lesson plans a teacher sends to the admin |
| `assessments_helper.php` | `assessment_types()`, `student_marks_raw()`, `subject_scores_from_raw()`, `at_show()` | Anything reading marks — a subject is built from its assessments |
| `settings_helper.php` | `school_name()`, `school_logo_url()`, `render_school_brand()` | The school's name or logo |
| `subjects_helper.php` | `teacher_subjects()`, `teacher_subject_ids()`, `save_teacher_subjects()` | Which subjects a teacher takes |
| `timetable_helper.php` | `tt_periods()`, `tt_class_grid()`, `tt_teacher_clash()`, `tt_render_grid()` | Anything to do with the timetable |
| `announcements_helper.php` | `get_announcements()` | Showing notices |
| `avatar_helper.php` | `avatar_url()` | A profile photo |

### Per-portal shared files

| File | What it does |
|---|---|
| `admin/sidebar.php` | Menu + top bar for admin **and** cashier (the menu is shorter for a cashier) |
| `admin/finance_common.php` | Included by every finance page: the admin+cashier guard, fee arithmetic, the tab bar |
| `teacher/teacher_classes.php` | Loads every class a teacher is attached to and works out which one is selected |
| `parent/parent_common.php` | The parent guard, which child is being viewed, `child_academics()` |
| `parent/footer.php` | Closes the page for parent pages |

None of these can be opened directly in a browser — they call
`internal_file_only(__FILE__)` and answer 404.

---

## 4. Logins and passwords

> **Passwords are stored as plain text.** `login.php` compares them directly.
> This is fine for a classroom demo on one machine and **not safe on the open
> internet**. See *Before going live* at the end.

### Accounts are created by database triggers

You do not write code to create a login. Adding a teacher or a student row
fires a trigger that makes the `users` row for you:

| Added | Trigger | Username becomes | Password becomes |
|---|---|---|---|
| Teacher | `before_teacher_insert` | their name, as typed | name, lowercase, no spaces, + `123` |
| Student | `before_student_insert` | their Student ID | name, letters only, lowercase, + `123` |

So a teacher called **Iqra** gets `Iqra` / `iqra123`, and a student
`ABC001/16` called Mohamed Omar gets `ABC001/16` / `mohamedomar123`.

Deleting the row fires a matching `before_*_delete` trigger that removes the
login too. Parents and cashiers are created by the admin pages instead, with a
default password (`parent123`, `cashier123`).

### There is no "log in as" choice

`users.username` is **UNIQUE**, so one username is one person with one role.
`login.php` reads the role off the account and redirects accordingly. Do not
add a role selector back — it only ever created a way to get it wrong.

---

## 5. Assessments — a subject is not one mark

A subject mark is built from several **assessments**. By default:

| Assessment | Marked out of | Worth |
|---|---|---|
| Assignment | 20 | 20% |
| Mid Exam | 30 | 30% |
| Final Exam | 50 | 50% |

```
subject score = SUM( mark / max_marks × weight )
```

Each paper is marked out of exactly what it is worth, so in practice the subject
score is just the three marks **added together**: 16 + 24 + 40 = **80 out of 100**.

The formula still divides by `max_marks`, so a school that prefers marking every
paper out of 100 can set *Out of* to 100 and get the same answer — the weights
do the scaling instead.

**Marks are `DECIMAL(6,2)`, not integers.** Out of 20 or 30, a half mark like
**17.5** is ordinary. Use `at_show()` to print one — it turns `18.00` into `18`
and `19.50` into `19.5`.

The admin sets these on **admin/assessments.php**. The active weights must
total exactly 100% — the page refuses to save otherwise. A type that already
holds marks cannot be deleted, only switched off.

Everything lives in **`assessments_helper.php`**:

```php
include_once '../assessments_helper.php';

$types = assessment_types($conn);              // the active columns
$raw   = student_marks_raw($conn, $student_id); // $raw[subject_id][type_id] = mark
$marks = subject_scores_from_raw($raw, $types); // [subject_id => score out of 100]

// one class in a single query, instead of one query per student
$all_raw = class_marks_raw($conn, $class_id);
```

`subject_scores_from_raw()` is the bridge to everything else: it returns
**only the subjects where every assessment is in**. Pages that used to do
`$marks[$sid] = $row['marks_obtained'];` now call it instead, and
`results_helper.php` keeps working untouched — a half-marked subject is simply
absent from the array, so it counts as unmarked.

In the database: `assessment_types`, plus `marks.assessment_type_id` with
`UNIQUE (student_id, subject_id, assessment_type_id)`. That unique key is the
whole fix — it used to be `UNIQUE (student_id, subject_id)`, which allowed only
one mark per subject.

> If `assessment_schema.sql` has not been run, every function above falls back
> to the old one-mark-per-subject behaviour, so nothing breaks.

---

## 6. The result rule (read this before touching marks)

A student's total, average, rank and PASS / FAIL are **only calculated when
every subject has a mark.** If even one is missing the student is `INCOMPLETE`
and all four are shown as a dash.

Why: averaging only the subjects that happen to be entered gives a false
result. A student with 76 in Maths and nothing else would otherwise show an
average of 76.0 and PASS.

All of this lives in **`results_helper.php`** and nowhere else. Use it:

```php
include_once '../results_helper.php';

$r = calc_result($marks, $subjects);   // $marks = [subject_id => mark]

$r['complete']   // true only when every subject has a mark
$r['total']      // null while incomplete
$r['average']    // null while incomplete
$r['status']     // 'PASS' | 'FAIL' | 'INCOMPLETE'
$r['missing']    // names of the subjects with no mark yet

$students = rank_students($students);  // incomplete students get rank = null
```

One trap worth knowing: a mark of **0 is a real mark**. The helper uses
`isset()` and not `empty()`, because `empty()` treats `0` as blank and would
hide genuine zeros.

---

## 7. Lesson plans

A teacher writes a plan, saves it as a **draft** while they work, then
**submits** it. The admin reads it and either approves it or sends it back with
a comment. A plan sent back can be edited and submitted again.

```
draft  ->  submitted  ->  approved
              ^              
              +--  needs_changes  (admin's comment, teacher edits, resubmits)
```

| | |
|---|---|
| Teacher | `teacher/lesson_plans.php` — list, write, read, print |
| Admin | `admin/lesson_plans.php` — read what was sent, approve or send back |
| Helper | `lesson_plans_helper.php` |
| Table | `lesson_plans` |

**The shape of the form lives in `lp_sections()`** — one list used by the
teacher's form, the admin's review page and the printable copy, so the three can
never drift apart. Adding a field to the plan means adding one line there and
one column to the table.

Rules the code enforces:

- The admin **never** sees a draft — only what has actually been sent.
- A teacher can only read, edit or delete **their own** plans. Every page checks
  `$plan['teacher_id'] === $teacher_id` before doing anything.
- A plan is locked while it is `submitted`, so it cannot change under the admin.
- Sending it back **requires** a comment; approving does not.
- Resubmitting clears the previous review, so the admin sees a clean plan.

The long sections hold **one item per line** and are shown as bullet points —
that keeps the form plain textareas a teacher can type into freely.

> Dates: an empty `<input type="date">` must be saved as `NULL`. An empty string
> in a MySQL `DATE` column becomes `0000-00-00`, which prints as
> *"30 Nov -0001"*. Use `lp_date()` to show one and `lp_date_input()` to fill
> the form field.

---

## 8. The database

`School_db1`. The main tables:

| Table | Holds |
|---|---|
| `users` | Every login: username, password, role |
| `students` | Students, each pointing at a class and a `users` row |
| `teachers` | Teachers, each pointing at a `users` row |
| `parents` | Parents, each pointing at a `users` row |
| `classes` | Grade, academic year, semester, homeroom teacher |
| `subjects` | Subject name, max marks, pass mark |
| `marks` | One row per student per subject |
| `attendance` | One row per student per day |
| `teacher_subjects` | Which subjects a teacher takes — **many to many** |
| `class_teachers` | Which classes a teacher takes — **many to many** |
| `timetable` + `periods` | The weekly timetable |
| `fees`, `payments`, `discounts`, `expenses`, `payroll` | Finance |
| `announcements` | Notices, with who they are aimed at |
| `school_settings` | Key/value: school name, motto, logo file name |

The `.sql` files in the root are the setup scripts. Import them in phpMyAdmin
in this order if you ever rebuild:

```
school_db1_schema.sql       (or my_school_db_schema.sql)
finance_schema.sql
finance_schema_v2.sql
parent_schema.sql
parent_schema_v2.sql
cashier_schema.sql
timetable_schema.sql
teacher_subjects_schema.sql
school_settings_schema.sql
assessment_schema.sql
assessment_max_change.sql
lesson_plan_schema.sql
demo_data.sql               (optional sample data)
```

### A teacher can take several subjects and several classes

Do not read `teachers.subject_id` — it only holds their *main* subject and is
kept for older code. Use the helpers:

```php
teacher_subjects($conn, $teacher_id);      // rows from `subjects`
teacher_subject_ids($conn, $teacher_id);   // just the ids
```

### Deleting rows: watch the foreign keys

Some foreign keys are `ON DELETE RESTRICT`, which means the database refuses
the delete while anything still points at the row. `admin/teachers.php` shows
the pattern — unhook everything inside a transaction, then delete:

```php
mysqli_begin_transaction($conn);
try {
    mysqli_query($conn, "UPDATE classes SET homeroom_teacher_id = NULL WHERE homeroom_teacher_id = $id");
    mysqli_query($conn, "UPDATE marks   SET entered_by = NULL          WHERE entered_by = $id");
    mysqli_query($conn, "DELETE FROM timetable WHERE teacher_id = $id");
    mysqli_query($conn, "DELETE FROM teachers  WHERE id = $id");
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
}
```

Without the `try`/`catch` a failed delete is a blank fatal-error page, because
PHP 8's mysqli throws an exception instead of returning `false`.

---

## 9. House style

- **Two spaces of meaning over two lines of cleverness.** Comments say *why*,
  not *what* — the code already says what.
- **Escape everything you print**: `htmlspecialchars($x ?? '')`. The `?? ''`
  matters: `htmlspecialchars(null)` prints a deprecation warning in PHP 8.1+,
  and if that lands inside a form's `value="..."` the warning text gets saved
  into the database. That has happened here.
- **Cast ids to int** before putting them in SQL: `$id = (int)$_GET['id'];`
- **New code should use prepared statements** (see `login.php`). Older pages
  build SQL strings; leave them unless you are already editing that query.
- **One shared thing, one file.** If you find yourself copying a block into a
  third page, move it into a helper instead.
- **Cache-bust shared assets** with the file's modified time, so nobody sees a
  stale copy:
  `<link rel="stylesheet" href="../responsive.css?v=<?php echo @filemtime(...); ?>">`

---

## 10. Recipes

### Add a page to a portal

1. Create the file, following the shape in section 2.
2. Add it to that portal's `sidebar.php` menu:

```php
<li>
    <a href="mypage.php" class="<?php echo $current_page == 'mypage.php' ? 'active' : ''; ?>">
        <span class="icon">📋</span>
        <span>My Page</span>
    </a>
</li>
```

### Add an AJAX endpoint

Use `require_role_json()` — a redirect is useless to JavaScript expecting JSON:

```php
include '../config.php';
require_role_json('admin');            // 403 + JSON when refused
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
```

### Add a school-wide setting

No table change needed — `school_settings` is key/value:

```php
set_setting($conn, 'school_phone', '0612345678');
$phone = get_setting($conn, 'school_phone', 'not set');
```

### Make a page work on a phone

Usually nothing to do: `responsive.css` and `responsive.js` are loaded by every
sidebar and already wrap tables in a horizontal scroller and turn the menu into
a drawer. Check it at 375px before you call it done.

---

## 11. Before going live

This is set up for a classroom on one machine. If it is ever put on a real
network, these need doing first — in this order:

1. **Hash the passwords.** Use `password_hash()` when saving and
   `password_verify()` in `login.php`. Drop the plain-text Password columns
   from `admin/students.php` and `admin/teachers.php`, and rewrite the two
   database triggers.
2. **Remove the demo accounts block** from `index.php` — it currently shows the
   admin password to anyone who opens the login page.
3. **Turn off error display**: in `config.php` set `display_errors` to `0` and
   read the PHP error log instead.
4. **Set a database password** for the MySQL user (it is currently blank).
5. **Serve over HTTPS**, then set the session cookie to `secure` + `httponly`.
6. **Finish moving to prepared statements** — most pages still build SQL by
   concatenating strings.
