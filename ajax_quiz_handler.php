<?php
// логика викторин
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// GET: загрузка вопросов
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $quiz_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT title, total_questions, description FROM quizzes WHERE ID_quiz = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    if (!$quiz) { echo json_encode(['error' => 'Викторина не найдена']); exit; }
    
    $questions = [];
    $q_stmt = $conn->prepare("SELECT ID_question, question_text FROM questions WHERE ID_quiz = ? ORDER BY ID_question");
    $q_stmt->bind_param("i", $quiz_id);
    $q_stmt->execute();
    $q_res = $q_stmt->get_result();
    while ($q = $q_res->fetch_assoc()) {
        $answers = [];
        $a_stmt = $conn->prepare("SELECT answer FROM answers WHERE ID_question = ? ORDER BY ID_answer");
        $a_stmt->bind_param("i", $q['ID_question']);
        $a_stmt->execute();
        $a_res = $a_stmt->get_result();
        while ($a = $a_res->fetch_assoc()) $answers[] = ['answer' => $a['answer']];
        $a_stmt->close();
        $questions[] = ['id' => $q['ID_question'], 'text' => $q['question_text'], 'answers' => $answers];
    }
    $q_stmt->close();
    echo json_encode(['quiz_id' => $quiz_id, 'title' => $quiz['title'], 'description' => $quiz['description'], 'total_questions' => (int)$quiz['total_questions'], 'questions' => $questions]);
    exit;
}

// POST: проверка ответов или оценка
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action']) && $input['action'] === 'rate') {
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Оценивать могут только авторизованные пользователи']); exit; }
        $quiz_id = (int)$input['quiz_id'];
        $rating = (int)$input['rating'];
        if ($rating < 1 || $rating > 5) { echo json_encode(['error' => 'Некорректная оценка']); exit; }
        $user_id = $_SESSION['user_id'];
        $check = $conn->prepare("SELECT ID_quiz_rating FROM quiz_ratings WHERE ID_user = ? AND ID_quiz = ?");
        $check->bind_param("ii", $user_id, $quiz_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) { echo json_encode(['error' => 'Вы уже оценили эту викторину']); exit; }
        $check->close();
        $insert = $conn->prepare("INSERT INTO quiz_ratings (ID_user, ID_quiz, rating) VALUES (?, ?, ?)");
        $insert->bind_param("iii", $user_id, $quiz_id, $rating);
        $success = $insert->execute();
        $insert->close();
        echo json_encode($success ? ['success' => true] : ['error' => 'Ошибка сохранения оценки']);
        exit;
    }
    if (!isset($input['quiz_id']) || !isset($input['answers'])) { echo json_encode(['error' => 'Неверный формат']); exit; }
    $quiz_id = (int)$input['quiz_id'];
    $userAnswers = $input['answers'];
    if (empty($userAnswers)) { echo json_encode(['error' => 'Нет ответов']); exit; }
    $question_ids = array_keys($userAnswers);
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $sql = "SELECT ID_question, correct_answer FROM questions WHERE ID_question IN ($placeholders) AND ID_quiz = ?";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($question_ids)) . 'i';
    $params = array_merge($question_ids, [$quiz_id]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $correct_map = [];
    while ($row = $result->fetch_assoc()) $correct_map[$row['ID_question']] = $row['correct_answer'];
    $stmt->close();
    $score = 0;
    foreach ($userAnswers as $qid => $ans) if (isset($correct_map[$qid]) && mb_strtolower(trim($ans)) === mb_strtolower(trim($correct_map[$qid]))) $score++;
    $total = count($userAnswers);
    $percentage = round(($score / $total) * 100);
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $insert = $conn->prepare("INSERT INTO user_results (ID_user, ID_quiz, score, total_possible, date_taken) VALUES (?, ?, ?, ?, NOW())");
        $insert->bind_param("iidd", $user_id, $quiz_id, $score, $total);
        $insert->execute();
        $insert->close();
    }
    echo json_encode(['score' => $score, 'total' => $total, 'percentage' => $percentage]);
    exit;
}
echo json_encode(['error' => 'Неверный запрос']);
?>