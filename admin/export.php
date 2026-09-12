<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

function csv_encode(array $rows, array $columns): string {
    $out = "\xEF\xBB\xBF"; // UTF-8 BOM
    $out .= implode(',', array_map('csv_escape', $columns)) . "\r\n";
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = csv_escape($row[$col] ?? '');
        }
        $out .= implode(',', $line) . "\r\n";
    }
    return $out;
}

function csv_escape(string $val): string {
    if (strpbrk($val, ",\"\r\n") !== false) {
        return '"' . str_replace('"', '""', $val) . '"';
    }
    return $val;
}

function fetch_dataset(string $dataset): array {
    switch ($dataset) {
        case 'questions':
            return db()->query("SELECT * FROM questions")->fetchAll();
        case 'case_studies':
            return db()->query("SELECT * FROM case_studies")->fetchAll();
        case 'candidates':
            return db()->query("SELECT id, roll_no, name, email, created_at FROM candidates")->fetchAll();
        case 'attempts':
            return db()->query("SELECT id, candidate_id, exam_id, started_at, finished_at, score, max_score, level_scores, status FROM attempts")->fetchAll();
        case 'answers':
            return db()->query("SELECT id, attempt_id, question_id, selected_answer, is_correct, answered_at FROM attempt_answers")->fetchAll();
        default:
            return [];
    }
}

function dataset_columns(string $dataset): array {
    switch ($dataset) {
        case 'questions':
            return ['id', 'level', 'qtype', 'case_study_id', 'language', 'version', 'difficulty', 'question_text', 'code_snippet', 'options', 'correct_answer', 'explanation', 'marks', 'status'];
        case 'case_studies':
            return ['id', 'title', 'scenario', 'context_code', 'language', 'created_at'];
        case 'candidates':
            return ['id', 'roll_no', 'name', 'email', 'created_at'];
        case 'attempts':
            return ['id', 'candidate_id', 'exam_id', 'started_at', 'finished_at', 'score', 'max_score', 'level_scores', 'status'];
        case 'answers':
            return ['id', 'attempt_id', 'question_id', 'selected_answer', 'is_correct', 'answered_at'];
        default:
            return [];
    }
}

function pdf_download(array $rows, array $columns, string $title): void {
    $fpdf = __DIR__ . '/../vendor/fpdf/fpdf.php';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $title) . '.pdf"');

    if (file_exists($fpdf) && class_exists('FPDF') === false) {
        require_once $fpdf;
    }
    if (!class_exists('FPDF', false)) {
        echo render_html_table($rows, $columns, $title);
        echo '<script>window.print();</script>';
        return;
    }

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, utf8_decode($title), 0, 1, 'C');
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 8);
    foreach ($columns as $col) {
        $pdf->Cell(48, 7, utf8_decode(ucwords(str_replace('_', ' ', $col))), 1, 0, 'C');
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);

    $limit = 200;
    foreach (array_slice($rows, 0, $limit) as $row) {
        foreach ($columns as $col) {
            $val = $row[$col] ?? '';
            $val = mb_substr($val, 0, 45);
            $pdf->Cell(48, 6, utf8_decode(strip_tags($val)), 1, 0, 'L');
        }
        $pdf->Ln();
    }

    $pdf->Output();
    exit;
}

function render_html_table(array $rows, array $columns, string $title): string {
    $h = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title></head><body>';
    $h .= '<h1>' . htmlspecialchars($title) . '</h1>';
    $h .= '<table border="1" cellpadding="4" cellspacing="0"><thead><tr>';
    foreach ($columns as $col) $h .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $col))) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach (array_slice($rows, 0, 200) as $row) {
        $h .= '<tr>';
        foreach ($columns as $col) $h .= '<td>' . htmlspecialchars($row[$col] ?? '') . '</td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></body></html>';
    return $h;
}

$datasets = ['questions', 'case_studies', 'candidates', 'attempts', 'answers', 'all'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['dataset'])) {
    $dataset = sanitize_input($_GET['dataset']);
    $format = sanitize_input($_GET['format'] ?? 'csv');

    if ($dataset === 'all') {
        if ($format !== 'json') {
            set_flash('error', 'The "all" dataset is only available in JSON format.');
            redirect('export.php');
        }
        $all = [];
        foreach (['questions', 'case_studies', 'candidates', 'attempts', 'answers'] as $ds) {
            $all[$ds] = fetch_dataset($ds);
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="codequest_all.json"');
        echo json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!in_array($dataset, $datasets)) {
        set_flash('error', 'Invalid dataset.');
        redirect('export.php');
    }

    $rows = fetch_dataset($dataset);
    $columns = dataset_columns($dataset);
    $title = ucwords(str_replace('_', ' ', $dataset));

    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $dataset . '.csv"');
            echo csv_encode($rows, $columns);
            break;
        case 'json':
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $dataset . '.json"');
            echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        case 'pdf':
            pdf_download($rows, $columns, $title);
            break;
        default:
            set_flash('error', 'Invalid format.');
            redirect('export.php');
    }
    exit;
}

admin_header('Export');
?>
<p class="text-muted">Passwords and password hashes are never exported. CSV exports include a UTF-8 BOM for Excel compatibility. The "all" dataset is JSON only.</p>
<div class="card">
    <h3>Download Dataset</h3>
    <form method="GET">
        <div class="form-group">
            <label for="dataset">Dataset</label>
            <select id="dataset" name="dataset">
                <?php foreach ($datasets as $ds): ?>
                <option value="<?= $ds ?>"><?= ucwords($ds) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="format">Format</label>
            <select id="format" name="format">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
                <option value="pdf">PDF</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Download</button>
    </form>
</div>
<?php admin_footer(); ?>