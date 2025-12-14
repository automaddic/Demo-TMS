<?php
// server/admin/actions/reset-handler.php

require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('check-in.php', $pdo);

date_default_timezone_set('America/New_York');

$action = $_POST['action'] ?? '';
$practiceDayId = isset($_POST['practice_day_id']) ? (int)$_POST['practice_day_id'] : 0;

if ($action === 'reset_user') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $isAltUser = ($source === 'alt_users') ? 1 : 0;

    if ($userId && $practiceDayId) {
        $pdo->beginTransaction();

        // Use both user_id and is_alt_user in the WHERE clause
        $stmt = $pdo->prepare("SELECT * FROM check_ins WHERE user_id = ? AND is_alt_user = ? AND practice_day_id = ?");
        $stmt->execute([$userId, $isAltUser, $practiceDayId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Archive then delete
            $cols = array_keys($row);
            $colsList = implode(',', $cols);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));

           try {
                $ins = $pdo->prepare("INSERT INTO check_ins_archive ($colsList, reset_at, reset_by) VALUES ($placeholders, NOW(), ?)");
                $vals = array_values($row);
                $resetBy = $_SESSION['user_id'] ?? null;
                $ins->execute(array_merge($vals, [$resetBy]));
            } catch (PDOException $e) {
                http_response_code(500);
                echo "Insert failed: " . $e->getMessage();
                $pdo->rollBack();
                exit;
            }

            $del = $pdo->prepare("DELETE FROM check_ins WHERE id = ?");
            $del->execute([$row['id']]);
        }

        $pdo->commit();
    }
    exit;
}
elseif ($action === 'reset_all') {
    if ($practiceDayId) {
        $pdo->beginTransaction();

        // No change here, since reset_all resets everything for practice day regardless of alt_user
        $stmt = $pdo->prepare("SELECT * FROM check_ins WHERE practice_day_id = ?");
        $stmt->execute([$practiceDayId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_keys($row);
            $colsList = implode(',', $cols);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));

            $ins = $pdo->prepare("INSERT INTO check_ins_archive ($colsList, reset_at, reset_by) VALUES ($placeholders, NOW(), ?)");
            $vals = array_values($row);
            $resetBy = $_SESSION['user_id'] ?? null;

            $ins->execute(array_merge($vals, [$resetBy]));

            $del = $pdo->prepare("DELETE FROM check_ins WHERE id = ?");
            $del->execute([$row['id']]);
        }

        $pdo->commit();
    }
    exit;
}
else {
    http_response_code(400);
    echo "Invalid reset action";
    exit;
}
