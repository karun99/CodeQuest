<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$question = [
    'id' => 0, 'level' => 'aptitude', 'qtype' => 'mcq', 'case_study_id' => null,
    'language' => '', 'version' => '', 'difficulty' => 'medium',
    'question_text' => '', 'code_snippet' => '', 'options' => '[]', 'correct_answer' => '',
    'explanation' => '', 'marks' => 1, 'status' => 'draft',
];

if ($id) {
    $stmt = db()->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) $question = array_merge($question, $row);
}

$options_list = json_decode($question['options'] ?? '[]', true) ?: [];
while (count($options_list) < 4) $options_list[] = '';

$case_studies = db()->query("SELECT id, title FROM case_studies ORDER BY title")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $data = [
        'level' => $_POST['level'] ?? 'aptitude',
        'qtype' => $_POST['qtype'] ?? 'mcq',
        'case_study_id' => ($_POST['case_study_id'] !== '') ? (int)$_POST['case_study_id'] : null,
        'language' => sanitize_input($_POST['language'] ?? ''),
        'version' => sanitize_input($_POST['version'] ?? ''),
        'difficulty' => sanitize_input($_POST['difficulty'] ?? 'medium'),
        'question_text' => sanitize_input($_POST['question_text'] ?? ''),
        'code_snippet' => trim($_POST['code_snippet'] ?? ''),
        'explanation' => sanitize_input($_POST['explanation'] ?? ''),
        'marks' => max(1, (int)($_POST['marks'] ?? 1)),
        'status' => sanitize_input($_POST['status'] ?? 'draft'),
    ];

    $qtype = $data['qtype'];
    $options_raw = [];
    for ($i = 0; $i < 4; $i++) {
        $options_raw[$i] = sanitize_input($_POST["option_$i"] ?? '');
    }

    if ($qtype === 'mcq' || $qtype === 'code_choice') {
        $non_empty = array_values(array_filter($options_raw, fn($o) => $o !== ''));
        if (count($non_empty) < 2) {
            $errors[] = 'MCQ options need at least 2 non-empty entries.';
        }
        $data['options'] = json_encode($options_raw);
        $data['correct_answer'] = (string)(int)($_POST['correct_option'] ?? -1);
        if ((int)$data['correct_answer'] < 0 || (int)$data['correct_answer'] >= 4) {
            $errors[] = 'Correct option must be within range.';
        }
    } else {
        $data['options'] = '[]';
        $data['correct_answer'] = sanitize_input($_POST['correct_answer'] ?? '');
        if ($data['correct_answer'] === '') {
            $errors[] = 'Fill-in answer cannot be empty.';
        }
    }

    if ($data['question_text'] === '') {
        $errors[] = 'Question text is required.';
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        $question = array_merge($question, $data);
        $options_list = $options_raw;
    } else {
        try {
            if ($id) {
                $sql = "UPDATE questions SET level=?, qtype=?, case_study_id=?, language=?, version=?, difficulty=?, question_text=?, code_snippet=?, options=?, correct_answer=?, explanation=?, marks=?, status=? WHERE id=?";
                $params = [$data['level'], $qtype, $data['case_study_id'], $data['language'], $data['version'], $data['difficulty'], $data['question_text'], $data['code_snippet'], $data['options'], $data['correct_answer'], $data['explanation'], $data['marks'], $data['status'], $id];
            } else {
                $sql = "INSERT INTO questions (level, qtype, case_study_id, language, version, difficulty, question_text, code_snippet, options, correct_answer, explanation, marks, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $params = [$data['level'], $qtype, $data['case_study_id'], $data['language'], $data['version'], $data['difficulty'], $data['question_text'], $data['code_snippet'], $data['options'], $data['correct_answer'], $data['explanation'], $data['marks'], $data['status']];
            }
            db()->prepare($sql)->execute($params);
            set_flash('success', 'Question saved.');
            redirect('questions.php');
        } catch (PDOException $e) {
            set_flash('error', 'Save failed: ' . $e->getMessage());
        }
    }
}

admin_header($id ? 'Edit Question #' . $id : 'New Question');
?>
<form method="POST" class="question-form">
    <?= csrf_field() ?>
    <div class="form-grid-2">
        <div class="form-group">
            <label for="level">Level</label>
            <select id="level" name="level">
                <option value="aptitude" <?= $question['level'] === 'aptitude' ? 'selected' : '' ?>>Aptitude</option>
                <option value="programming" <?= $question['level'] === 'programming' ? 'selected' : '' ?>>Programming</option>
                <option value="debugging" <?= $question['level'] === 'debugging' ? 'selected' : '' ?>>Debugging</option>
            </select>
        </div>
        <div class="form-group">
            <label for="qtype">Question Type</label>
            <select id="qtype" name="qtype">
                <option value="mcq" <?= $question['qtype'] === 'mcq' ? 'selected' : '' ?>>MCQ</option>
                <option value="fill" <?= $question['qtype'] === 'fill' ? 'selected' : '' ?>>Fill-in-the-blank</option>
                <option value="code_choice" <?= $question['qtype'] === 'code_choice' ? 'selected' : '' ?>>Code Choice</option>
            </select>
        </div>
        <div class="form-group">
            <label for="difficulty">Difficulty</label>
            <select id="difficulty" name="difficulty">
                <option value="easy" <?= $question['difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                <option value="medium" <?= $question['difficulty'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="hard" <?= $question['difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" <?= $question['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $question['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>
        <div class="form-group">
            <label for="language">Language</label>
            <input type="text" id="language" name="language" value="<?= e($question['language']) ?>" placeholder="e.g. javascript">
        </div>
        <div class="form-group">
            <label for="version">Version</label>
            <input type="text" id="version" name="version" value="<?= e($question['version']) ?>" placeholder="e.g. node20">
        </div>
        <div class="form-group">
            <label for="marks">Marks</label>
            <input type="number" id="marks" name="marks" value="<?= (int)$question['marks'] ?>" min="1">
        </div>
        <div class="form-group">
            <label for="case_study_id">Case Study</label>
            <select id="case_study_id" name="case_study_id">
                <option value="">— None —</option>
                <?php foreach ($case_studies as $cs): ?>
                <option value="<?= $cs['id'] ?>" <?= $question['case_study_id'] == $cs['id'] ? 'selected' : '' ?>><?= e($cs['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="question_text">Question Text *</label>
        <textarea id="question_text" name="question_text" rows="4" required><?= e($question['question_text']) ?></textarea>
    </div>

    <div class="form-group">
        <label for="code_snippet">Code Snippet</label>
        <textarea id="code_snippet" name="code_snippet" rows="8" class="mono-textarea" placeholder="Optional code to display with the question..."><?= e($question['code_snippet']) ?></textarea>
    </div>

    <div id="fillFields" style="<?= $question['qtype'] === 'fill' ? '' : 'display:none' ?>">
        <div class="form-group">
            <label for="correct_answer">Correct Answer *</label>
            <input type="text" id="correct_answer" name="correct_answer" value="<?= e($question['correct_answer']) ?>" placeholder="e.g. n+1">
        </div>
    </div>

    <div id="choiceFields" style="<?= $question['qtype'] === 'fill' ? 'display:none' : '' ?>">
        <h3>Options</h3>
        <div class="options-editor">
            <?php foreach ($options_list as $i => $opt): ?>
            <div class="option-row">
                <input type="text" name="option_<?= $i ?>" value="<?= e($opt) ?>" placeholder="Option <?= $i + 1 ?>">
                <label class="radio-label">
                    <input type="radio" name="correct_option" value="<?= $i ?>" <?= (int)$question['correct_answer'] === $i ? 'checked' : '' ?>>
                    Correct
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-group">
        <label for="explanation">Explanation</label>
        <textarea id="explanation" name="explanation" rows="3"><?= e($question['explanation']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Save Question</button>
</form>

<script>
const qtype = document.getElementById('qtype');
const fillFields = document.getElementById('fillFields');
const choiceFields = document.getElementById('choiceFields');
function toggleFields() {
    const isFill = qtype.value === 'fill';
    if (fillFields) fillFields.style.display = isFill ? '' : 'none';
    if (choiceFields) choiceFields.style.display = isFill ? 'none' : '';
}
qtype.addEventListener('change', toggleFields);
toggleFields();
</script>
<?php admin_footer(); ?>