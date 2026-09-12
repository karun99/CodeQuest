<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

function strip_bom(string $s): string {
    if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
        return substr($s, 3);
    }
    return $s;
}

function parse_csv_string(string $content): array {
    $content = strip_bom($content);
    $lines = explode("\r\n", str_replace("\n", "\r\n", $content));
    $rows = [];
    $headers = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $fields = str_getcsv($line);
        if ($headers === null) {
            $headers = array_map('trim', $fields);
            continue;
        }
        $assoc = [];
        foreach ($headers as $i => $h) {
            if ($h === '') continue;
            $assoc[$h] = $fields[$i] ?? '';
        }
        $rows[] = $assoc;
    }
    return $rows;
}

function validate_question_row(array $row, int $index, array &$errors): ?array {
    $level = $row['level'] ?? '';
    $qtype = $row['qtype'] ?? '';
    $question_text = $row['question_text'] ?? '';

    if (!in_array($level, ['aptitude', 'programming', 'debugging'])) {
        $errors[] = "Row {$index}: invalid level '{$level}'";
        return null;
    }
    if (!in_array($qtype, ['mcq', 'fill', 'code_choice'])) {
        $errors[] = "Row {$index}: invalid qtype '{$qtype}'";
        return null;
    }
    if ($question_text === '') {
        $errors[] = "Row {$index}: question_text is required";
        return null;
    }
    if (!in_array($row['status'] ?? 'draft', ['draft', 'published'])) {
        $errors[] = "Row {$index}: invalid status";
        return null;
    }
    if (!in_array($row['difficulty'] ?? 'medium', ['easy', 'medium', 'hard'])) {
        $errors[] = "Row {$index}: invalid difficulty";
        return null;
    }
    if ($qtype !== 'fill') {
        $opts = json_decode($row['options'] ?? '[]', true);
        if (!is_array($opts) || count(array_filter($opts, fn($o) => trim((string)$o) !== '')) < 2) {
            $errors[] = "Row {$index}: options must be a JSON array with at least 2 non-empty entries";
            return null;
        }
    } else {
        if (trim($row['correct_answer'] ?? '') === '') {
            $errors[] = "Row {$index}: fill questions need a correct_answer";
            return null;
        }
    }

    return [
        'level' => $level,
        'qtype' => $qtype,
        'case_study_id' => ($row['case_study_id'] ?? '') !== '' ? (int)$row['case_study_id'] : null,
        'language' => $row['language'] ?? '',
        'version' => $row['version'] ?? '',
        'difficulty' => $row['difficulty'] ?? 'medium',
        'question_text' => $question_text,
        'code_snippet' => $row['code_snippet'] ?? '',
        'options' => $qtype !== 'fill' ? json_encode(json_decode($row['options'] ?? '[]', true), JSON_UNESCAPED_UNICODE) : '[]',
        'correct_answer' => $qtype !== 'fill' ? (string)($row['correct_answer'] ?? '') : trim($row['correct_answer'] ?? ''),
        'explanation' => $row['explanation'] ?? '',
        'marks' => max(1, (int)($row['marks'] ?? 1)),
        'status' => $row['status'] ?? 'draft',
    ];
}

function validate_candidate_row(array $row, int $index, array &$errors): ?array {
    $roll = trim($row['roll_no'] ?? '');
    $name = trim($row['name'] ?? '');
    if ($roll === '' || $name === '') {
        $errors[] = "Row {$index}: roll_no and name are required";
        return null;
    }
    return [
        'roll_no' => $roll,
        'name' => $name,
        'email' => trim($row['email'] ?? '') ?: null,
    ];
}

$preview = null;
$preview_entity = '';
$preview_rows = [];
$preview_errors = [];
$file_label = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity = sanitize_input($_POST['entity'] ?? '');
    $mode = sanitize_input($_POST['mode'] ?? 'insert');
    $fmt = sanitize_input($_POST['format'] ?? 'csv');

    if (!empty($_POST['rows_json'])) {
        $rows_json = html_entity_decode($_POST['rows_json'], ENT_QUOTES, 'UTF-8');
        $decoded = json_decode($rows_json, true);
        if (!is_array($decoded)) {
            set_flash('error', 'Stale preview — please parse the file again.');
            redirect('import.php');
        }
        $valid = $decoded;
        $errors = [];
        $preview_entity = $entity;
        $preview_rows = $valid;
        $preview_errors = [];
        $preview_mode = $mode;
        $preview = true;
        $commit_source = true;
    } else {
        $raw = '';

        if (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ($_FILES['file']['size'] > MAX_FILE_SIZE) {
                set_flash('error', 'File exceeds 5 MB limit.');
                redirect('import.php');
            }
            $raw = file_get_contents($_FILES['file']['tmp_name']);
            $file_label = $_FILES['file']['name'];
        } elseif (trim($_POST['paste'] ?? '') !== '') {
            $raw = $_POST['paste'];
            $file_label = 'pasted input';
        } else {
            set_flash('error', 'Provide a file or paste content.');
            redirect('import.php');
        }

        $raw = strip_bom($raw);

        if (!in_array($entity, ['questions', 'candidates']) || !in_array($fmt, ['csv', 'json']) || !in_array($mode, ['insert', 'upsert', 'replace'])) {
            set_flash('error', 'Invalid import parameters.');
            redirect('import.php');
        }

        if ($fmt === 'json') {
            $parsed = json_decode($raw, true);
            if (!is_array($parsed)) {
                set_flash('error', 'Invalid JSON payload.');
                redirect('import.php');
            }
            $rows = $parsed;
        } else {
            $rows = parse_csv_string($raw);
        }

        if (count($rows) > MAX_IMPORT_ROWS) {
            set_flash('error', 'File exceeds ' . MAX_IMPORT_ROWS . ' rows limit.');
            redirect('import.php');
        }

        $valid = [];
        $errors = [];
        foreach ($rows as $i => $row) {
            $r = $entity === 'questions' ? validate_question_row($row, $i + 2, $errors) : validate_candidate_row($row, $i + 2, $errors);
            if ($r) $valid[] = $r;
        }

        $preview_entity = $entity;
        $preview_rows = $valid;
        $preview_errors = $errors;
        $preview_mode = $mode;
        $preview = true;
    }

    if (isset($_POST['commit']) && $preview_entity) {
        if ($errors) {
            set_flash('error', 'Cannot commit: validation errors present.');
            redirect('import.php');
        }

        $admin_id = $_SESSION['admin_id'];
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $commit_errors = [];

        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($entity === 'questions') {
                if ($mode === 'replace') {
                    $pdo->exec("DELETE FROM questions");
                }
                $ins = $pdo->prepare("INSERT INTO questions (level, qtype, case_study_id, language, version, difficulty, question_text, code_snippet, options, correct_answer, explanation, marks, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                foreach ($preview_rows as $r) {
                    $ins->execute([$r['level'], $r['qtype'], $r['case_study_id'], $r['language'], $r['version'], $r['difficulty'], $r['question_text'], $r['code_snippet'], $r['options'], $r['correct_answer'], $r['explanation'], $r['marks'], $r['status']]);
                    $inserted++;
                }
            } elseif ($entity === 'candidates') {
                $ins = $pdo->prepare("INSERT INTO candidates (roll_no, name, email, password_hash) VALUES (?,?,?,?)");
                $upd = $pdo->prepare("UPDATE candidates SET name = ?, email = ? WHERE roll_no = ?");
                foreach ($preview_rows as $r) {
                    $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                    try {
                        $ins->execute([$r['roll_no'], $r['name'], $r['email'], $hash]);
                        $inserted++;
                    } catch (PDOException $e) {
                        if ($mode === 'upsert' && $e->getCode() == 23000) {
                            $upd->execute([$r['name'], $r['email'], $r['roll_no']]);
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    }
                }
            }

            $pdo->prepare("INSERT INTO import_log (admin_id, entity, format, mode, inserted, updated, skipped, errors) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$admin_id, $entity, $fmt, $mode, $inserted, $updated, $skipped, $errors ? implode('; ', array_slice($errors, 0, 20)) : null]);

            $pdo->commit();
            set_flash('success', "Import committed: {$inserted} inserted, {$updated} updated, {$skipped} skipped.");
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash('error', 'Import failed: ' . $e->getMessage());
        }
        redirect('import.php');
    }
}

admin_header('Import');
?>
<p class="text-muted">Import questions or candidates from CSV or JSON. Files up to 5 MB, max 5,000 rows. Password hashes are never imported; candidates without passwords get random ones.</p>

<form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid-2">
        <div class="form-group">
            <label for="entity">Entity</label>
            <select id="entity" name="entity">
                <option value="questions">Questions</option>
                <option value="candidates">Candidates</option>
            </select>
        </div>
        <div class="form-group">
            <label for="format">Format</label>
            <select id="format" name="format">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
            </select>
        </div>
        <div class="form-group">
            <label for="mode">Mode</label>
            <select id="mode" name="mode">
                <option value="insert">Insert</option>
                <option value="upsert">Upsert</option>
                <option value="replace">Replace (questions only)</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="file">Upload File</label>
        <input type="file" id="file" name="file" accept=".csv,.json,.txt">
    </div>
    <div class="form-group">
        <label for="paste">Or Paste Content</label>
        <textarea id="paste" name="paste" rows="6" class="mono-textarea" placeholder="CSV or JSON content..."></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Parse & Preview</button>
</form>

<?php if ($preview): ?>
<div class="card import-preview">
    <h3>Preview — <?= ucfirst($preview_entity) ?> (<?= count($preview_rows) ?> valid rows, <?= count($preview_errors) ?> errors)</h3>

    <?php if ($preview_errors): ?>
    <div class="alert alert-danger">
        <strong>Validation errors (input must be fixed):</strong>
        <ul><?php foreach (array_slice($preview_errors, 0, 20) as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
    </div>
    <?php endif; ?>

    <?php if ($preview_rows): ?>
    <table class="data-table">
        <thead>
            <tr>
                <?php if ($preview_entity === 'questions'): ?>
                <th>Level</th><th>Type</th><th>Difficulty</th><th>Question Text</th><th>Marks</th><th>Status</th>
                <?php else: ?>
                <th>Roll No</th><th>Name</th><th>Email</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($preview_rows, 0, 20) as $r): ?>
            <tr>
                <?php if ($preview_entity === 'questions'): ?>
                <td><?= e($r['level']) ?></td>
                <td><?= e($r['qtype']) ?></td>
                <td><?= e($r['difficulty']) ?></td>
                <td class="q-cell"><?= e(mb_substr($r['question_text'], 0, 90)) ?></td>
                <td><?= $r['marks'] ?></td>
                <td><?= e($r['status']) ?></td>
                <?php else: ?>
                <td><?= e($r['roll_no']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['email'] ?? '—') ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="POST" onsubmit="return confirm('Commit this import?');">
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="<?= e($preview_entity) ?>">
        <input type="hidden" name="format" value="<?php echo isset($fmt) ? e($fmt) : 'json'; ?>">
        <input type="hidden" name="mode" value="<?= e($preview_mode ?? 'insert') ?>">
        <input type="hidden" name="rows_json" value="<?= e(json_encode($preview_rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
        <input type="hidden" name="commit" value="1">
        <button type="submit" class="btn btn-success" <?= $preview_errors ? 'disabled' : '' ?>>Commit Import</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php admin_footer(); ?>