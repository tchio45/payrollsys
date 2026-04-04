<?php
/**
 * Paginate an array of records.
 *
 * @param array  $records     Full array of records
 * @param int    $perPage     Items per page (default 15)
 * @param string $pageParam   GET parameter name for page number
 * @return array ['data' => sliced records, 'current' => page, 'total' => total pages, 'count' => total records, 'param' => pageParam]
 */
function paginate(array $records, int $perPage = 15, string $pageParam = 'page'): array {
    $totalRecords = count($records);
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $currentPage = max(1, min((int)($_GET[$pageParam] ?? 1), $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'data'    => array_slice($records, $offset, $perPage),
        'current' => $currentPage,
        'total'   => $totalPages,
        'count'   => $totalRecords,
        'param'   => $pageParam,
    ];
}

/**
 * Render pagination controls HTML.
 * Preserves existing GET parameters in links.
 *
 * @param array $pagination Result from paginate()
 */
function renderPagination(array $pagination): void {
    if ($pagination['total'] <= 1) return;

    $current = $pagination['current'];
    $total   = $pagination['total'];
    $param   = $pagination['param'];

    // Build base query string preserving other GET params
    $queryParams = $_GET;

    echo '<div class="pagination">';
    echo '<span class="pagination-info">Page ' . $current . ' / ' . $total . ' (' . $pagination['count'] . ' records)</span>';
    echo '<div class="pagination-controls">';

    // Previous
    if ($current > 1) {
        $queryParams[$param] = $current - 1;
        echo '<a href="?' . htmlspecialchars(http_build_query($queryParams)) . '" class="btn btn-sm btn-primary">&laquo; Previous</a> ';
    }

    // Page numbers (show max 7 pages around current)
    $start = max(1, $current - 3);
    $end = min($total, $current + 3);

    for ($i = $start; $i <= $end; $i++) {
        $queryParams[$param] = $i;
        $activeClass = ($i === $current) ? 'btn-primary' : 'btn-outline';
        echo '<a href="?' . htmlspecialchars(http_build_query($queryParams)) . '" class="btn btn-sm ' . $activeClass . '">' . $i . '</a> ';
    }

    // Next
    if ($current < $total) {
        $queryParams[$param] = $current + 1;
        echo '<a href="?' . htmlspecialchars(http_build_query($queryParams)) . '" class="btn btn-sm btn-primary">Next &raquo;</a>';
    }

    echo '</div></div>';
}
?>
