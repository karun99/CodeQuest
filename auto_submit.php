<?php
require_once __DIR__ . '/includes/helpers.php';

function auto_submit(int $attempt_id): void {
    $att = db()->prepare("SELECT id, exam_id, candidate_id FROM attempts WHERE id = ? AND status = 'in_progress'");
    $att->execute([$attempt_id]);
    $attempt = $att->fetch();
    if (!$attempt) return;

    $exam = db()->prepare("SELECT id, passing_percent FROM exams WHERE id = ?");
    $exam->execute([$attempt['exam_id']]);
    $ex = $exam->fetch();
    if (!$ex) return;

    $qids = [];
    $eq = db()->prepare("SELECT question_id FROM exam_questions WHERE exam_id = ? ORDER BY sort_order");
    $eq->execute([$attempt['exam_id']]);
    while ($r = $eq->fetch()) {
        $qids[] = $r['question_id'];
    }

    $score = 0;
    $max_score = 0;
    $level_scores = ['aptitude' => [0, 0], 'programming' => [0, 0], 'debugging' => [0, 0]];

    foreach ($qids as $qid) {
        $q = db()->prepare("SELECT level, qtype, options, correct_answer, marks FROM questions WHERE id = ?");
        $q->execute([$qid]);
        $ques = $q->fetch();
        if (!$ques) continue;

        $max_score += $ques['marks'];
        $level_scores[$ques['level']][1] += $ques['marks'];

        $ans = db()->prepare("SELECT selected_answer FROM attempt_answers WHERE attempt_id = ? AND question_id = ?");
        $ans->execute([$attempt_id, $qid]);
        $row = $ans->fetch();

        if ($row && $row['selected_answer'] !== null && $row['selected_answer'] !== '') {
            $correct = false;
            $sel = $row['selected_answer'];

            if ($ques['qtype'] === 'fill') {
                $correct = strtolower(trim($sel)) === strtolower(trim($ques['correct_answer']));
            } else {
                $correct = ($sel === $ques['correct_answer']);
            }

            if ($correct) {
                $score += $ques['marks'];
                $level_scores[$ques['level']][0] += $ques['marks'];
            }

            db()->prepare("UPDATE attempt_answers SET is_correct = ? WHERE attempt_id = ? AND question_id = ?")
                ->execute([$correct ? 1 : 0, $attempt_id, $qid]);
        }
    }

    $percent = $max_score > 0 ? round(($score / $max_score) * 100, 2) : 0;
    $status = $percent >= $ex['passing_percent'] ? 'PASS' : 'FAIL';

    db()->prepare("UPDATE attempts SET finished_at = NOW(), score = ?, max_score = ?, level_scores = ?, status = 'completed' WHERE id = ?")
        ->execute([$score, $max_score, json_encode($level_scores), $attempt_id]);
}
