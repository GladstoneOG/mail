<?php
/**
 * Database helper functions for LAN Mail.
 * PHP 5.6 compatible, SQL Server via sqlsrv.
 */

/**
 * Execute a query and return the statement resource.
 */
function db_query($conn, $sql, $params = array()) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log('SQL Error: ' . print_r(sqlsrv_errors(), true));
        return false;
    }
    return $stmt;
}

/**
 * Fetch all rows as associative arrays.
 */
function db_fetch_all($conn, $sql, $params = array()) {
    $stmt = db_query($conn, $sql, $params);
    if (!$stmt) return array();
    $rows = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

/**
 * Fetch a single row.
 */
function db_fetch_one($conn, $sql, $params = array()) {
    $stmt = db_query($conn, $sql, $params);
    if (!$stmt) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row ? $row : null;
}

/**
 * Fetch a single scalar value.
 */
function db_fetch_scalar($conn, $sql, $params = array()) {
    $row = db_fetch_one($conn, $sql, $params);
    if (!$row) return null;
    $values = array_values($row);
    return $values[0];
}

/**
 * Execute a statement (INSERT/UPDATE/DELETE) and return rows affected.
 */
function db_execute($conn, $sql, $params = array()) {
    $stmt = db_query($conn, $sql, $params);
    if (!$stmt) return false;
    $rows = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    return $rows;
}

/**
 * Insert a row and return the new identity value.
 */
function db_insert_get_id($conn, $sql, $params = array()) {
    // Append SCOPE_IDENTITY() to the same batch so it runs in the same scope
    $combinedSql = $sql . "; SELECT SCOPE_IDENTITY() AS new_id";
    $stmt = sqlsrv_query($conn, $combinedSql, $params);
    if ($stmt === false) {
        error_log('SQL Error in insert: ' . print_r(sqlsrv_errors(), true));
        return false;
    }
    // Move to the SELECT result set
    sqlsrv_next_result($stmt);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if ($row && isset($row['new_id']) && $row['new_id'] !== null) {
        return intval($row['new_id']);
    }
    return false;
}

/**
 * Check if a table exists in the current database.
 */
function db_table_exists($conn, $tableName) {
    $sql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
    $row = db_fetch_one($conn, $sql, array($tableName));
    return $row && intval($row['cnt']) > 0;
}
