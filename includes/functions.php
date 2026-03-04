<?php
/**
 * Calculate decimal hours between two time strings.
 * Handles shifts crossing midnight.
 * Subtracts 1 hour for break if total duration exceeds 5 hours.
 */
function calculateTotalHours($start_time, $end_time) {
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    
    if ($end < $start) {
        $end->modify('+1 day');
    }
    
    $interval = $start->diff($end);
    $total = $interval->h + ($interval->i / 60);

    // Subtract 1 hour for lunch/break if shift is longer than 5 hours
    if ($total > 5) {
        $total = $total - 1;
    }

    return $total;
}

/**
 * Sanitize and format database logs for UI display.
 */
function formatLogs($logs) {
    foreach ($logs as &$l) {
        $l['formatted_date'] = date("M d, Y", strtotime($l['log_date']));
        $l['formatted_start'] = date("h:i A", strtotime($l['start_time']));
        $l['formatted_end'] = date("h:i A", strtotime($l['end_time']));
        $l['tasks'] = htmlspecialchars($l['tasks']);
    }
    return $logs;
}

/**
 * Calculate completion metrics based on rendered hours.
 * Skips already logged dates (Absents/Future shifts) to provide an accurate estimate.
 */
function getCompletionMetrics($conn, $user_id, $goal_hours = 486, $standard_daily_hours = 8) {
    // 1. Get total rendered hours
    $sql = "SELECT SUM(total_hours) as rendered FROM entries WHERE user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $rendered = (float)($stmt->fetchColumn() ?: 0);
    
    // 2. Fetch all future dates already logged (Present or Absent)
    $sql = "SELECT log_date, status, total_hours FROM entries WHERE user_id = :user_id ORDER BY log_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $all_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $logged_dates = array_column($all_entries, 'log_date');
    $present_hours = array_filter($all_entries, fn($e) => $e['status'] === 'Present');
    
    // Calculate Average (last 7 sessions)
    $recent = array_slice($present_hours, 0, 7);
    $avg_daily = count($recent) > 0 ? array_sum(array_column($recent, 'total_hours')) / count($recent) : 0;

    $remaining_hours = max(0, $goal_hours - $rendered);
    $current_date = new DateTime();
    $working_days_count = 0;
    
    // 3. Project forward day by day
    while ($remaining_hours > 0) {
        $current_date->modify('+1 day');
        $date_str = $current_date->format('Y-m-d');
        if ($current_date->format('N') > 5) continue;
        if (in_array($date_str, $logged_dates)) continue;
        $remaining_hours -= $standard_daily_hours;
        $working_days_count++;
    }

    // Required hours to finish in current remaining workdays
    $req_daily = $working_days_count > 0 ? (max(0, $goal_hours - $rendered) / $working_days_count) : 0;

    return [
        'rendered_hours' => $rendered,
        'remaining_hours' => max(0, $goal_hours - $rendered),
        'remaining_days' => $working_days_count,
        'estimated_date' => $rendered >= $goal_hours ? 'Goal Reached' : $current_date->format('M d, Y'),
        'is_complete' => $rendered >= $goal_hours,
        'avg_daily' => $avg_daily,
        'req_daily' => $req_daily
    ];
}
?>