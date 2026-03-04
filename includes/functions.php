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
?>