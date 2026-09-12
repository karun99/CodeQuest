<?php
require_once __DIR__ . '/includes/helpers.php';

if (php_sapi_name() === 'cli') {
    echo "CodeQuest sample data seeder\n";
}

try {
    db()->query("SELECT 1");
} catch (PDOException $e) {
    die('Database not installed. Run install.php first.');
}

$cs_stmt = db()->prepare("INSERT INTO case_studies (title, scenario, language, context_code) VALUES (?,?,?,?)");
$cs1 = null;
$cs2 = null;

$existing_cs = (int)db()->query("SELECT COUNT(*) FROM case_studies")->fetchColumn();
if ($existing_cs === 0) {
    $cs_stmt->execute([
        'E-Commerce Order Processing',
        'An online store calculates order totals. Customers report that the final total is sometimes inflated.',
        'javascript',
        "function calcTotal(cart) {\n  let total = 0;\n  for (let i = 0; i <= cart.length; i++) {\n    total += cart[i].price;\n  }\n  return total;\n}",
    ]);
    $cs1 = (int)db()->lastInsertId();

    $cs_stmt->execute([
        'Bank Account Ledger',
        'A bank processes daily transactions. The system misreports the closing balance at end of day.',
        'python',
        "def closing_balance(transactions, opening=0):\n    bal = opening\n    for t in transactions:\n        if t['type'] == 'credit':\n            bal += t['amount']\n        else:\n            bal -= t['amount']\n    return bal",
    ]);
    $cs2 = (int)db()->lastInsertId();
    echo "Seeded case studies.\n";
}

$q = (int)db()->query("SELECT COUNT(*) FROM questions")->fetchColumn();
if ($q === 0) {
    $ins = db()->prepare("INSERT INTO questions (level, qtype, case_study_id, language, version, difficulty, question_text, code_snippet, options, correct_answer, explanation, marks, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $questions = [
        ['aptitude', 'mcq', null, 'javascript', 'node20', 'easy', 'What is the time complexity of binary search?', '', '["O(n)","O(log n)","O(n log n)","O(1)"]', '1', 'Binary search halves the search space each step, giving O(log n).', 1, 'published'],
        ['aptitude', 'mcq', null, 'python', '3.11', 'easy', 'What does the following snippet return?\n\nprint(type([]))', '', '["<class \'list\'>","<class \'tuple\'>","<class \'dict\'>","<class \'array\'>"]', '0', '[] creates a list.', 1, 'published'],
        ['aptitude', 'fill', null, 'javascript', 'node20', 'medium', 'Complete the condition to check if x is even.', 'if (x % 2 ____ 0) { console.log("even"); }', '[]', '===', 'Modulo 2 equals 0 for even numbers.', 2, 'published'],
        ['programming', 'fill', $cs1, 'javascript', 'node20', 'medium', 'Fix the loop bounds so calcTotal sums every item exactly once.', 'function calcTotal(cart) {\n  let total = 0;\n  for (let i = 0; i ____ cart.length; i++) {\n    total += cart[i].price;\n  }\n  return total;\n}', '[]', '<', 'The loop must run while i < cart.length to avoid the undefined extra item.', 3, 'published'],
        ['programming', 'code_choice', null, 'python', '3.11', 'hard', 'Which snippet correctly returns the sum of the first n natural numbers?', '', '["def s(n):\\n    return sum(range(n+1))","def s(n):\\n    return sum(range(1, n))","def s(n):\\n    total = 0\\n    for i in range(n):\\n        total += i\\n    return total","def s(n):\\n    return n*(n+1)//2 - n"]', '0', 'range(n+1) yields 0..n, which sums to the first n natural numbers plus 0.', 3, 'published'],
        ['debugging', 'mcq', $cs2, 'python', '3.11', 'medium', 'The ledger gives the wrong closing balance when the opening balance is non-zero. What is the bug?', 'def closing_balance(transactions, opening=0):\n    bal = opening\n    for t in transactions:\n        if t["type"] == "credit":\n            bal += t["amount"]\n        else:\n            bal -= t["amount"]\n    return bal', '["No bug","Mutation of opening balance","Off-by-one in loop","Debits and credits are swapped"]', '0', 'The logic is correct for this model; the reported issue is likely elsewhere or non-deterministic.', 2, 'published'],
        ['debugging', 'mcq', $cs1, 'javascript', 'node20', 'hard', 'Customers see an inflated total. What is the root cause?', 'function calcTotal(cart) {\n  let total = 0;\n  for (let i = 0; i <= cart.length; i++) {\n    total += cart[i].price;\n  }\n  return total;\n}', '["Using <= instead of < in the loop adds an extra iteration with undefined price","The total is not initialised","cart is empty","The function returns a string"]', '0', 'i <= cart.length runs one extra iteration; cart[i].price is undefined, but undefined + total can behave unexpectedly.', 3, 'published'],
        ['debugging', 'fill', null, 'python', '3.11', 'easy', 'What value does this loop print that is wrong for n = 0?', 'def count_down(n):\n    while n:\n        print(n)\n        n -= 1', '[]', '0', 'For n = 0 the loop body never runs, which is correct; for negative n it never terminates.', 1, 'published'],
    ];

    foreach ($questions as $qrow) {
        $ins->execute($qrow);
    }
    $q = count($questions);
    echo "Seeded questions.\n";
}

$e = (int)db()->query("SELECT COUNT(*) FROM exams")->fetchColumn();
if ($e === 0 && $q > 0) {
    db()->prepare("INSERT INTO exams (title, duration_minutes, passing_percent, is_active) VALUES ('CodeQuest Coding Assessment', 25, 40, 1)")->execute();
    $exam_id = (int)db()->lastInsertId();
    $qids = db()->query("SELECT id FROM questions WHERE status = 'published' ORDER BY id")->fetchAll();
    $ins = db()->prepare("INSERT INTO exam_questions (exam_id, question_id, sort_order) VALUES (?,?,?)");
    foreach ($qids as $i => $r) {
        $ins->execute([$exam_id, $r['id'], $i]);
    }
    echo "Seeded exam.\n";
}

echo "Seed complete.\n";