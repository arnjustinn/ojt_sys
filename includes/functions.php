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
    $sql = "SELECT log_date FROM entries WHERE user_id = :user_id AND log_date >= CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $logged_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $remaining_hours = max(0, $goal_hours - $rendered);
    $current_date = new DateTime();
    $working_days_count = 0;
    
    // 3. Project forward day by day
    while ($remaining_hours > 0) {
        $current_date->modify('+1 day');
        $date_str = $current_date->format('Y-m-d');
        
        // Skip weekends (Saturday = 6, Sunday = 7)
        if ($current_date->format('N') > 5) continue;
        
        // Skip dates already in the database (like future absences)
        if (in_array($date_str, $logged_dates)) continue;
        
        // Subtract hours for this available workday
        $remaining_hours -= $standard_daily_hours;
        $working_days_count++;
    }

    return [
        'rendered_hours' => $rendered,
        'remaining_hours' => max(0, $goal_hours - $rendered),
        'remaining_days' => $working_days_count,
        'estimated_date' => $rendered >= $goal_hours ? 'Goal Reached' : $current_date->format('M d, Y'),
        'is_complete' => $rendered >= $goal_hours
    ];
}
?>